<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Carbon\CarbonImmutable;

use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-4.3 counts the days left in the current month, today included', function (string $now, int $daysLeft): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user, 'UTC');
    travelTo(CarbonImmutable::parse($now, 'UTC'));

    expect(BudgetScenario::read($user, $book, '2026-11')['days_left'])->toBe($daysLeft);
})->with([
    'first day' => ['2026-11-01 00:00:00', 30],
    'a day in the month' => ['2026-11-25 10:00:00', 6],
    'last day' => ['2026-11-30 23:59:59', 1],
]);

it('REQ-4.3 gives no days left for a past or a future month', function (string $month): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    expect(BudgetScenario::read($user, $book, $month)['days_left'])->toBeNull();
})->with(['2026-10', '2026-12', '2000-01']);

it('REQ-4.3 uses the book\'s timezone', function (): void {
    $user = User::factory()->create();
    $tunis = LedgerScenario::personalBook($user, 'Africa/Tunis');
    $utc = LedgerScenario::personalBook($user, 'UTC');
    travelTo(CarbonImmutable::parse('2026-11-30 23:30:00', 'UTC'));

    expect(BudgetScenario::read($user, $tunis, '2026-11')['days_left'])->toBeNull()
        ->and(BudgetScenario::read($user, $tunis, '2026-12')['days_left'])->toBe(31)
        ->and(BudgetScenario::read($user, $utc, '2026-11')['days_left'])->toBe(1)
        ->and(BudgetScenario::read($user, $utc, '2026-12')['days_left'])->toBeNull();
});
