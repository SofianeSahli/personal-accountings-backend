<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\BudgetStatus;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-3.4 gives each line its percent and status from exact integers', function (int $planned, int $actual, ?int $percent, string $status): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => $planned]);

    if ($actual > 0) {
        LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, $actual, '2026-11-10');
    }

    expect(BudgetScenario::read($user, $book, '2026-11')['lines'][0])->toMatchArray([
        'planned' => $planned,
        'actual' => $actual,
        'percent' => $percent,
        'status' => $status,
    ]);
})->with([
    'nothing spent' => [100_000, 0, 0, 'ok'],
    '84 %' => [100_000, 84_999, 84, 'ok'],
    '85 %' => [100_000, 85_000, 85, 'warning'],
    'just under 85 %, rounded up would be 85' => [3, 2, 66, 'ok'],
    '100 %' => [100_000, 100_000, 100, 'warning'],
    'just above 100 %, floored to 100' => [100_000, 100_001, 100, 'over'],
    '101 %' => [100_000, 101_000, 101, 'over'],
    'planned 0, nothing spent' => [0, 0, null, 'ok'],
    'planned 0, something spent' => [0, 1, null, 'over'],
    'the largest plan, fully used' => [999_999_999_999, 999_999_999_999, 100, 'warning'],
]);

it('REQ-6.2 decides the status without rounding', function (int $planned, int $actual, BudgetStatus $status): void {
    expect(BudgetStatus::of($planned, $actual))->toBe($status);
})->with([
    'exactly 85 % of an odd plan' => [20, 17, BudgetStatus::Warning],
    'a millime under 85 %' => [1_000_001, 850_000, BudgetStatus::Ok],
    'the largest amounts' => [999_999_999_999, 1_999_999_999_998, BudgetStatus::Over],
]);
