<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

/**
 * @param  list<string>  $codes
 * @return array<string, mixed>
 */
function budgetLines(array $codes): array
{
    return ['lines' => array_map(fn (string $code): array => ['category' => $code, 'planned' => 1_000], $codes)];
}

it('REQ-2.3 to REQ-2.6 refuse a line on a category that cannot be planned', function (array $codes, array $fields): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    BudgetScenario::save($user, $book, '2026-11', budgetLines($codes))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => $fields]]);

    expect(BudgetScenario::linesOf($book, '2026-11'))->toBe([]);
})->with([
    'unknown' => [['food', 'nope'], ['lines.1.category' => ['lines.category.not_found']]],
    'a leaf' => [[LedgerScenario::SUPERMARKET], ['lines.0.category' => ['lines.category.not_plannable']]],
    'an income top' => [['income'], ['lines.0.category' => ['lines.category.not_expense']]],
    'a saving group' => [['savings.reserves'], ['lines.0.category' => ['lines.category.not_expense']]],
    'a leaf of income' => [[LedgerScenario::SALARY], ['lines.0.category' => ['lines.category.not_plannable']]],
    'the same twice' => [['food', 'food'], ['lines.0.category' => ['lines.category.distinct'], 'lines.1.category' => ['lines.category.distinct']]],
    'a parent then its child' => [['food', 'transport', 'food.groceries'], ['lines.2.category' => ['lines.category.overlaps']]],
    'a child then its parent' => [['food.groceries', 'food'], ['lines.0.category' => ['lines.category.overlaps']]],
    'two children of a planned parent' => [['food.groceries', 'food.eating_out', 'food'], [
        'lines.0.category' => ['lines.category.overlaps'],
        'lines.1.category' => ['lines.category.overlaps'],
    ]],
    'several wrong lines' => [['nope', 'food', LedgerScenario::SUPERMARKET, 'income'], [
        'lines.0.category' => ['lines.category.not_found'],
        'lines.2.category' => ['lines.category.not_plannable'],
        'lines.3.category' => ['lines.category.not_expense'],
    ]],
]);

it('REQ-2.4 accepts two groups of the same top', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    BudgetScenario::save($user, $book, '2026-11', budgetLines(['food.groceries', 'food.eating_out']))->assertOk();
});

it('REQ-2.7 refuses a retired category new to the month', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-10', ['transport' => 1_000]);
    Category::query()->where('code', 'transport')->update(['retired_at' => now()]);

    BudgetScenario::save($user, $book, '2026-11', budgetLines(['food', 'transport']))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['lines.1.category' => ['lines.category.retired']]]]);
});

it('REQ-2.7 keeps a retired category that was already a line of the month', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['transport' => 1_000]);
    Category::query()->where('code', 'transport')->update(['retired_at' => now()]);

    BudgetScenario::save($user, $book, '2026-11', ['lines' => [['category' => 'transport', 'planned' => 2_000]]])
        ->assertOk()
        ->assertJsonPath('data.lines.0', ['category' => 'transport', 'planned' => 2_000, 'actual' => 0, 'percent' => 0, 'status' => 'ok', 'retired' => true]);
});
