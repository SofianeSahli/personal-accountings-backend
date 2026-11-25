<?php

declare(strict_types=1);

use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-2.10 keeps the last of two saves of the same month, whole', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();

    BudgetScenario::save($owner, $book, '2026-11', [
        'expected_income' => 1_000,
        'planned_saving' => 2_000,
        'lines' => [['category' => 'food', 'planned' => 1], ['category' => 'transport', 'planned' => 2]],
    ])->assertOk();
    BudgetScenario::save($member, $book, '2026-11', [
        'expected_income' => 3_000,
        'lines' => [['category' => 'food.groceries', 'planned' => 3]],
    ])->assertOk();

    $plan = BudgetPlan::query()->sole();

    expect(BudgetScenario::linesOf($book, '2026-11'))->toBe([['category' => 'food.groceries', 'planned' => 3]])
        ->and([$plan->expected_income, $plan->planned_saving])->toBe([3_000, null]);
});
