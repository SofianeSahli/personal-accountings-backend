<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\CategoryType;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;

/**
 * @return array<string, string>
 */
function categoryReader(?User $user = null): array
{
    Auth::forgetGuards();

    return IdentityScenario::bearer(IdentityScenario::tokenFor($user ?? User::factory()->create()));
}

/**
 * @return array{food: Category, groceries: Category, supermarket: Category, other: Category, income: Category}
 */
function smallCategoryTree(): array
{
    $income = Category::factory()->code('income')->create(['type' => CategoryType::Income, 'emoji' => '💼', 'position' => 1]);
    $food = Category::factory()->code('food')->create(['emoji' => '🛒', 'position' => 0]);
    $groceries = Category::factory()->under($food, 'groceries')->create(['emoji' => '🏪']);
    $other = Category::factory()->under($groceries, 'other', 1)->create(['emoji' => '📝']);
    $supermarket = Category::factory()->under($groceries, 'supermarket', 0)->create(['emoji' => null]);
    $work = Category::factory()->under($income, 'work')->create(['emoji' => '💼']);
    Category::factory()->under($work, 'other')->create(['emoji' => '📝']);

    return ['food' => $food, 'groceries' => $groceries, 'supermarket' => $supermarket, 'other' => $other, 'income' => $income];
}

it('REQ-1.1 REQ-1.2 answers the whole tree in order, with every field (token mode)', function (): void {
    smallCategoryTree();

    getJson('/api/v1/categories', categoryReader())
        ->assertOk()
        ->assertExactJson(['data' => [
            [
                'code' => 'food',
                'translation_key' => 'categories.food.label',
                'emoji' => '🛒',
                'type' => 'expense',
                'retired' => false,
                'groups' => [[
                    'code' => 'food.groceries',
                    'translation_key' => 'categories.food.groceries.label',
                    'emoji' => '🏪',
                    'retired' => false,
                    'leaves' => [
                        [
                            'code' => 'food.groceries.supermarket',
                            'translation_key' => 'categories.food.groceries.supermarket.label',
                            'emoji' => null,
                            'retired' => false,
                            'is_other' => false,
                        ],
                        [
                            'code' => 'food.groceries.other',
                            'translation_key' => 'categories.food.groceries.other.label',
                            'emoji' => '📝',
                            'retired' => false,
                            'is_other' => true,
                        ],
                    ],
                ]],
            ],
            [
                'code' => 'income',
                'translation_key' => 'categories.income.label',
                'emoji' => '💼',
                'type' => 'income',
                'retired' => false,
                'groups' => [[
                    'code' => 'income.work',
                    'translation_key' => 'categories.income.work.label',
                    'emoji' => '💼',
                    'retired' => false,
                    'leaves' => [[
                        'code' => 'income.work.other',
                        'translation_key' => 'categories.income.work.other.label',
                        'emoji' => '📝',
                        'retired' => false,
                        'is_other' => true,
                    ]],
                ]],
            ],
        ]]);
});

it('REQ-1.5 answers the same body to every account, in cookie and token mode', function (): void {
    smallCategoryTree();

    $token = getJson('/api/v1/categories', categoryReader())->assertOk();
    $otherToken = getJson('/api/v1/categories', categoryReader())->assertOk();
    $cookie = WebBrowser::signedInAs(User::factory()->create())->getJson('/api/v1/categories')->assertOk();

    expect($cookie->getContent())->toBe($token->getContent())
        ->and($otherToken->getContent())->toBe($token->getContent())
        ->and($cookie->headers->get('ETag'))->toBe($token->headers->get('ETag'));
});

it('REQ-1.6 still returns retired categories, and a retired group makes its leaves retired', function (): void {
    $tree = smallCategoryTree();
    $tree['other']->update(['retired_at' => now()]);
    $tree['income']->update(['retired_at' => now()]);

    getJson('/api/v1/categories', categoryReader())
        ->assertOk()
        ->assertJsonPath('data.0.retired', false)
        ->assertJsonPath('data.0.groups.0.leaves.0.retired', false)
        ->assertJsonPath('data.0.groups.0.leaves.1.code', 'food.groceries.other')
        ->assertJsonPath('data.0.groups.0.leaves.1.retired', true)
        ->assertJsonPath('data.1.retired', true)
        ->assertJsonPath('data.1.groups.0.retired', true)
        ->assertJsonPath('data.1.groups.0.leaves.0.retired', true);
});

it('REQ-1.6 shows the leaves of a retired group as retired', function (): void {
    $tree = smallCategoryTree();
    $tree['groceries']->update(['retired_at' => now()]);

    getJson('/api/v1/categories', categoryReader())
        ->assertOk()
        ->assertJsonPath('data.0.retired', false)
        ->assertJsonPath('data.0.groups.0.retired', true)
        ->assertJsonPath('data.0.groups.0.leaves.0.retired', true)
        ->assertJsonPath('data.0.groups.0.leaves.1.retired', true);
});

it('REQ-1.3 refuses a caller who is not signed in (token mode)', function (): void {
    getJson('/api/v1/categories')
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('REQ-1.3 refuses a caller who is not signed in (cookie mode)', function (): void {
    WebBrowser::open()->getJson('/api/v1/categories')
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('REQ-1.4 sends an ETag and answers 304 with no body to a matching If-None-Match', function (): void {
    smallCategoryTree();
    $headers = categoryReader();

    $first = getJson('/api/v1/categories', $headers)->assertOk();
    $etag = (string) $first->headers->get('ETag');

    expect($etag)->not->toBe('')
        ->and((string) $first->headers->get('Cache-Control'))->toContain('no-cache')
        ->and((string) $first->headers->get('Cache-Control'))->toContain('private');

    $notModified = getJson('/api/v1/categories', [...$headers, 'If-None-Match' => $etag]);

    expect($notModified->getStatusCode())->toBe(304)
        ->and($notModified->getContent())->toBe('');
});

it('REQ-1.4 changes the ETag when the list changes', function (): void {
    $tree = smallCategoryTree();
    $headers = categoryReader();
    $etag = (string) getJson('/api/v1/categories', $headers)->headers->get('ETag');

    $tree['supermarket']->update(['emoji' => '🥫']);

    $changed = getJson('/api/v1/categories', [...$headers, 'If-None-Match' => $etag])
        ->assertOk()
        ->assertJsonPath('data.0.groups.0.leaves.0.emoji', '🥫');

    expect($changed->headers->get('ETag'))->not->toBe($etag);
});

it('REQ-1.1 serves the shipped list once it is synced', function (): void {
    artisan('ledger:sync-categories')->assertSuccessful();

    $data = getJson('/api/v1/categories', categoryReader())->assertOk()->json('data');

    expect($data)->toHaveCount(11)
        ->and(array_sum(array_map(fn (array $top): int => count($top['groups']), $data)))->toBe(33)
        ->and($data[0]['code'])->toBe('food')
        ->and($data[10]['code'])->toBe('savings')
        ->and($data[10]['type'])->toBe('saving');
});
