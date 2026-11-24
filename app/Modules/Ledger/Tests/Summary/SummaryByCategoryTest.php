<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    LedgerScenario::categories();
});

/**
 * @param  array<string, int>  $positions
 */
function placeCategories(array $positions): void
{
    foreach ($positions as $code => $position) {
        Category::query()->where('code', $code)->update(['position' => $position]);
    }
}

/**
 * @return list<array<string, mixed>>
 */
function categoriesOf(User $user, Book $book): array
{
    return getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), HouseholdScenario::as($user))
        ->assertOk()
        ->json('data.categories');
}

/**
 * @param  list<array<string, mixed>>  $nodes
 * @return array<string, array<string, mixed>>
 */
function byCode(array $nodes): array
{
    $keyed = [];

    foreach ($nodes as $node) {
        $keyed[(string) $node['category']] = $node;
    }

    return $keyed;
}

it('REQ-2.1 returns the categories with entries as a tree, each parent adding up its children', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 850_000, '2026-11-01');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 50_000, '2026-11-02');
    LedgerScenario::spend($book, LedgerScenario::RESTAURANTS, 62_500, '2026-11-15');

    expect(categoriesOf($user, $book))->toBe([
        [
            'category' => 'food',
            'type' => 'expense',
            'total' => 962_500,
            'entries' => 3,
            'children' => [
                [
                    'category' => 'food.eating_out',
                    'type' => 'expense',
                    'total' => 62_500,
                    'entries' => 1,
                    'children' => [
                        ['category' => LedgerScenario::RESTAURANTS, 'type' => 'expense', 'total' => 62_500, 'entries' => 1, 'children' => [], 'other_names' => []],
                    ],
                    'other_names' => [],
                ],
                [
                    'category' => 'food.groceries',
                    'type' => 'expense',
                    'total' => 900_000,
                    'entries' => 2,
                    'children' => [
                        ['category' => LedgerScenario::SUPERMARKET, 'type' => 'expense', 'total' => 900_000, 'entries' => 2, 'children' => [], 'other_names' => []],
                    ],
                    'other_names' => [],
                ],
            ],
            'other_names' => [],
        ],
    ]);
});

it('REQ-2.2 orders each level as in the category tree', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    placeCategories([
        'savings' => 0, 'income' => 1, 'food' => 2,
        'food.groceries' => 0, 'food.eating_out' => 1,
        LedgerScenario::GROCERIES_OTHER => 0, LedgerScenario::SUPERMARKET => 1,
    ]);

    foreach ([LedgerScenario::RESTAURANTS, LedgerScenario::SUPERMARKET, LedgerScenario::SALARY, LedgerScenario::GROCERIES_OTHER, LedgerScenario::EMERGENCY_FUND] as $code) {
        LedgerScenario::spend($book, $code, 1_000, '2026-11-10', null, $code === LedgerScenario::GROCERIES_OTHER ? 'Marché' : null);
    }

    $tree = categoriesOf($user, $book);

    expect(array_column($tree, 'category'))->toBe(['savings', 'income', 'food'])
        ->and(array_column($tree[2]['children'], 'category'))->toBe(['food.groceries', 'food.eating_out'])
        ->and(array_column($tree[2]['children'][0]['children'], 'category'))->toBe([LedgerScenario::GROCERIES_OTHER, LedgerScenario::SUPERMARKET])
        ->and(array_column($tree, 'type'))->toBe(['saving', 'income', 'expense']);
});

it('REQ-2.2 includes retired categories', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-10');
    Category::query()->whereIn('code', ['food', 'food.groceries', LedgerScenario::SUPERMARKET])->update(['retired_at' => now()]);

    $tree = categoriesOf($user, $book);

    expect($tree[0]['category'])->toBe('food')
        ->and($tree[0]['children'][0]['children'][0])->toMatchArray(['category' => LedgerScenario::SUPERMARKET, 'total' => 1_000]);
});

it('REQ-2.3 lists the names typed under an Other leaf, case merged, accents apart, largest first', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $type = function (string $name, int $amount, string $at) use ($book): void {
        LedgerScenario::spend($book, LedgerScenario::GROCERIES_OTHER, $amount, '2026-11-10', null, $name)
            ->forceFill(['updated_at' => $at])->saveQuietly();
    };
    $type('Marché', 50_000, '2026-11-10 10:00:00');
    $type('MARCHÉ', 5_000, '2026-11-12 10:00:00');
    $type('marché', 5_000, '2026-11-11 10:00:00');
    $type('Epicerie', 70_000, '2026-11-10 10:00:00');
    $type('Épicerie', 12_500, '2026-11-10 10:00:00');
    $type('Boucherie', 12_500, '2026-11-10 10:00:00');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-10');

    $groceries = categoriesOf($user, $book)[0]['children'][0];
    $leaves = byCode($groceries['children']);

    expect($leaves[LedgerScenario::GROCERIES_OTHER]['other_names'])->toBe([
        ['name' => 'Epicerie', 'total' => 70_000, 'entries' => 1],
        ['name' => 'MARCHÉ', 'total' => 60_000, 'entries' => 3],
        ['name' => 'Boucherie', 'total' => 12_500, 'entries' => 1],
        ['name' => 'Épicerie', 'total' => 12_500, 'entries' => 1],
    ])
        ->and($leaves[LedgerScenario::GROCERIES_OTHER]['total'])->toBe(155_000)
        ->and($leaves[LedgerScenario::SUPERMARKET]['other_names'])->toBe([])
        ->and($groceries['other_names'])->toBe([]);
});

it('REQ-2.3 keeps the names of each Other leaf apart', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::leaf('food.eating_out.other');
    LedgerScenario::spend($book, LedgerScenario::GROCERIES_OTHER, 1_000, '2026-11-10', null, 'Marché');
    LedgerScenario::spend($book, 'food.eating_out.other', 2_000, '2026-11-10', null, 'Marché');

    $groups = categoriesOf($user, $book)[0]['children'];
    $leaves = byCode([...$groups[0]['children'], ...$groups[1]['children']]);

    expect($leaves[LedgerScenario::GROCERIES_OTHER]['other_names'])->toBe([['name' => 'Marché', 'total' => 1_000, 'entries' => 1]])
        ->and($leaves['food.eating_out.other']['other_names'])->toBe([['name' => 'Marché', 'total' => 2_000, 'entries' => 1]]);
});
