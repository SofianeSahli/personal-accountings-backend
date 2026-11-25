<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-2.8 refuses a malformed plan with the field codes of the contract', function (array $body, array $fields): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    BudgetScenario::save($user, $book, '2026-11', $body)
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => $fields]]);

    expect(BudgetPlan::query()->count())->toBe(0);
})->with([
    'lines missing' => [[], ['lines' => ['lines.present']]],
    'lines not an array' => [['lines' => 'food'], ['lines' => ['lines.array']]],
    'more than 100 lines' => [['lines' => array_map(fn (int $i): array => ['category' => "c{$i}", 'planned' => 1], range(0, 100))], ['lines' => ['lines.max']]],
    'category missing' => [['lines' => [['planned' => 1]]], ['lines.0.category' => ['lines.category.required']]],
    'category empty' => [['lines' => [['category' => '  ', 'planned' => 1]]], ['lines.0.category' => ['lines.category.required']]],
    'category not a string' => [['lines' => [['category' => 12, 'planned' => 1]]], ['lines.0.category' => ['lines.category.string']]],
    'planned missing' => [['lines' => [['category' => 'food']]], ['lines.0.planned' => ['lines.planned.required']]],
    'planned as a string' => [['lines' => [['category' => 'food', 'planned' => '125']]], ['lines.0.planned' => ['lines.planned.integer']]],
    'planned as a decimal' => [['lines' => [['category' => 'food', 'planned' => 12.5]]], ['lines.0.planned' => ['lines.planned.integer']]],
    'planned negative' => [['lines' => [['category' => 'food', 'planned' => -1]]], ['lines.0.planned' => ['lines.planned.min']]],
    'planned above the maximum' => [['lines' => [['category' => 'food', 'planned' => 1_000_000_000_000]]], ['lines.0.planned' => ['lines.planned.max']]],
    'income as a string' => [['expected_income' => '125', 'lines' => []], ['expected_income' => ['expected_income.integer']]],
    'income as a decimal' => [['expected_income' => 12.5, 'lines' => []], ['expected_income' => ['expected_income.integer']]],
    'income negative' => [['expected_income' => -1, 'lines' => []], ['expected_income' => ['expected_income.min']]],
    'income above the maximum' => [['expected_income' => 1_000_000_000_000, 'lines' => []], ['expected_income' => ['expected_income.max']]],
    'saving as a string' => [['planned_saving' => '1', 'lines' => []], ['planned_saving' => ['planned_saving.integer']]],
    'saving negative' => [['planned_saving' => -5, 'lines' => []], ['planned_saving' => ['planned_saving.min']]],
    'saving above the maximum' => [['planned_saving' => 1_000_000_000_000, 'lines' => []], ['planned_saving' => ['planned_saving.max']]],
    'duplicate category' => [
        ['lines' => [['category' => 'food', 'planned' => 1], ['category' => 'transport', 'planned' => 1], ['category' => 'food', 'planned' => 2]]],
        ['lines.0.category' => ['lines.category.distinct'], 'lines.2.category' => ['lines.category.distinct']],
    ],
    'errors on several lines, by position' => [
        ['lines' => [['category' => 'food', 'planned' => 1], ['category' => 'transport', 'planned' => -1], ['planned' => 2]]],
        ['lines.1.planned' => ['lines.planned.min'], 'lines.2.category' => ['lines.category.required']],
    ],
]);

it('REQ-2.8 checks the category codes only once the fields are well formed', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    BudgetScenario::save($user, $book, '2026-11', ['lines' => [['category' => 'nope', 'planned' => 1], ['category' => 'food', 'planned' => -1]]])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['lines.1.planned' => ['lines.planned.min']]]]);
});

it('REQ-2.8 accepts the bounds of every amount', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    BudgetScenario::save($user, $book, '2026-11', [
        'expected_income' => 999_999_999_999,
        'planned_saving' => 0,
        'lines' => [['category' => 'food', 'planned' => 999_999_999_999], ['category' => 'transport', 'planned' => 0]],
    ])->assertOk()
        ->assertJsonPath('data.income.expected', 999_999_999_999)
        ->assertJsonPath('data.saving.planned', 0)
        ->assertJsonPath('data.expenses.planned', 999_999_999_999);
});
