<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    LedgerScenario::categories();
});

it('REQ-2.1 lists the book entries newest date first, then newest created first', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $old = Transaction::factory()->inBook($book)->by($user)->create(['date' => '2026-10-01']);
    $firstOfDay = Transaction::factory()->inBook($book)->by($user)->create(['date' => '2026-11-20', 'created_at' => now()->subHour()]);
    $lastOfDay = Transaction::factory()->inBook($book)->by($user)->create(['date' => '2026-11-20']);
    Transaction::factory()->create(['date' => '2026-11-21']);

    getJson(LedgerScenario::transactionsUrl($book), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$lastOfDay->id, $firstOfDay->id, $old->id])
        ->assertJsonPath('meta.per_page', 25);
});

it('REQ-2.1 pages by 25 with a cursor', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    Transaction::factory()->count(27)->inBook($book)->by($user)->create();

    $first = getJson(LedgerScenario::transactionsUrl($book), HouseholdScenario::as($user))->assertOk();
    expect($first->json('data'))->toHaveCount(25);

    $second = getJson(LedgerScenario::transactionsUrl($book, '?cursor='.$first->json('meta.next_cursor')), HouseholdScenario::as($user))->assertOk();
    expect($second->json('data'))->toHaveCount(2)
        ->and($second->json('meta.next_cursor'))->toBeNull()
        ->and(array_intersect($first->json('data.*.id'), $second->json('data.*.id')))->toBe([]);
});

it('REQ-2.2 filters by date range, category at any level, type and author', function (string $query, array $expected): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();
    $make = fn (string $key, string $date, string $code, User $by) => Transaction::factory()->inBook($book)->by($by)
        ->in(LedgerScenario::category($code), str_ends_with($code, '.other') ? 'Divers' : null)
        ->create(['date' => $date, 'comment' => $key]);
    $make('a', '2026-10-31', LedgerScenario::SUPERMARKET, $owner);
    $make('b', '2026-11-01', LedgerScenario::GROCERIES_OTHER, $member);
    $make('c', '2026-11-15', LedgerScenario::RESTAURANTS, $owner);
    $make('d', '2026-11-30', LedgerScenario::SALARY, $member);
    $make('e', '2026-12-01', LedgerScenario::EMERGENCY_FUND, $owner);
    $query = str_replace(['{owner}', '{member}'], [$owner->id, $member->id], $query);

    $comments = getJson(LedgerScenario::transactionsUrl($book, '?'.$query), HouseholdScenario::as($member))
        ->assertOk()
        ->json('data.*.comment');

    expect($comments)->toBe($expected);
})->with([
    'no filter' => ['', ['e', 'd', 'c', 'b', 'a']],
    'november' => ['from=2026-11-01&to=2026-11-30', ['d', 'c', 'b']],
    'from only' => ['from=2026-11-30', ['e', 'd']],
    'one day' => ['from=2026-11-15&to=2026-11-15', ['c']],
    'top category' => ['category=food', ['c', 'b', 'a']],
    'group' => ['category=food.groceries', ['b', 'a']],
    'leaf' => ['category=food.groceries.other', ['b']],
    'type income' => ['type=income', ['d']],
    'type saving' => ['type=saving', ['e']],
    'author' => ['author={member}', ['d', 'b']],
    'combined' => ['type=expense&author={owner}&from=2026-11-01', ['c']],
    'unknown author' => ['author=0193b0c1-2d3e-7f40-8a1b-2c3d4e5f6a7b', []],
]);

it('REQ-2.2 does not treat underscores in a code as wildcards', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::leaf('food.eating_out.cafes');
    LedgerScenario::leaf('food.eatingxout.cafes');
    Transaction::factory()->inBook($book)->in(LedgerScenario::category('food.eatingxout.cafes'))->create();

    getJson(LedgerScenario::transactionsUrl($book, '?category=food.eating_out'), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('REQ-2.2 validates the filters', function (string $query, string $field, string $code): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    getJson(LedgerScenario::transactionsUrl($book, '?'.$query), HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [$field => [$code]]]]);
})->with([
    'bad from' => ['from=2026-13-01', 'from', 'from.date_format'],
    'bad to' => ['to=tomorrow', 'to', 'to.date_format'],
    'to before from' => ['from=2026-11-10&to=2026-11-09', 'to', 'to.after_or_equal'],
    'unknown category' => ['category=nothing', 'category', 'category.not_found'],
    'bad type' => ['type=transfer', 'type', 'type.in'],
    'bad author' => ['author=me', 'author', 'author.uuid'],
]);

it('REQ-2.4 leaves deleted entries out', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $kept = Transaction::factory()->inBook($book)->create();
    Transaction::factory()->inBook($book)->create()->delete();

    getJson(LedgerScenario::transactionsUrl($book), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$kept->id]);
});

it('REQ-2.5 names authors who left the household, with one name query per page (cookie mode)', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();
    Transaction::factory()->count(5)->inBook($book)->by($member)->create();
    Transaction::factory()->count(5)->inBook($book)->by($owner)->create();
    HouseholdScenario::leave($member);
    $browser = WebBrowser::signedInAs($owner);

    DB::enableQueryLog();
    $response = $browser->getJson(LedgerScenario::transactionsUrl($book))->assertOk();
    $userQueries = array_filter(
        DB::getQueryLog(),
        fn (array $entry): bool => str_contains($entry['query'], 'from `users` where `id` in'),
    );

    expect($response->collect('data')->pluck('created_by.name')->unique()->sort()->values()->all())->toBe(['Amira', 'Sofiane'])
        ->and($userQueries)->toHaveCount(1);
});

it('REQ-2.3 shows one entry', function (): void {
    $user = User::factory()->create(['name' => 'Sofiane']);
    $book = LedgerScenario::personalBook($user);
    $entry = Transaction::factory()->inBook($book)->by($user)
        ->in(LedgerScenario::category(LedgerScenario::GROCERIES_OTHER), 'Épicerie')
        ->create(['amount' => 4_250, 'date' => '2026-11-02']);

    getJson(LedgerScenario::transactionsUrl($book, '/'.$entry->id), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.id', $entry->id)
        ->assertJsonPath('data.amount', 4_250)
        ->assertJsonPath('data.date', '2026-11-02')
        ->assertJsonPath('data.category', LedgerScenario::GROCERIES_OTHER)
        ->assertJsonPath('data.other_name', 'Épicerie')
        ->assertJsonPath('data.created_by.name', 'Sofiane');
});
