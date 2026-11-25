<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-3.3 counts the book\'s live entries of the month, as the summary does', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => 100_000, 'transport.fuel' => 50_000]);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-01');
    LedgerScenario::spend($book, LedgerScenario::RESTAURANTS, 2_000, '2026-11-30');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 4_000, '2026-11-15')->delete();
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 8_000, '2026-10-31');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 16_000, '2026-12-01');
    LedgerScenario::spend(LedgerScenario::personalBook($user), LedgerScenario::SUPERMARKET, 32_000, '2026-11-10');
    LedgerScenario::spend($book, LedgerScenario::SALARY, 64_000, '2026-11-10');
    LedgerScenario::spend($book, LedgerScenario::EMERGENCY_FUND, 128_000, '2026-11-10');

    $data = BudgetScenario::read($user, $book, '2026-11');

    expect(array_column($data['lines'], 'actual', 'category'))->toBe(['food' => 3_000, 'transport.fuel' => 0])
        ->and($data['expenses']['actual'])->toBe(3_000)
        ->and($data['income']['actual'])->toBe(64_000)
        ->and($data['saving']['actual'])->toBe(128_000);
});

it('REQ-3.3 counts a recurring rule only through the entries it has made', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['transport' => 100_000]);
    $petrol = LedgerScenario::category(BudgetScenario::PETROL);
    $made = RecurringRule::factory()->inBook($book)->in($petrol)->startingOn('2026-11-05')->create(['amount' => 7_000]);
    Transaction::factory()->inBook($book)->in($petrol)->create([
        'amount' => 7_000,
        'date' => '2026-11-05',
        'recurring_rule_id' => $made->id,
        'rule_due_on' => '2026-11-05',
    ]);
    RecurringRule::factory()->inBook($book)->in($petrol)->startingOn('2026-11-28')->create(['amount' => 50_000]);

    expect(BudgetScenario::read($user, $book, '2026-11')['lines'][0])->toMatchArray(['category' => 'transport', 'actual' => 7_000]);
});

it('REQ-3.3 counts a group line from its leaves and a top line from all its groups', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food.groceries' => 10_000, 'transport' => 10_000]);
    LedgerScenario::leaf('transport.public.bus');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-02');
    LedgerScenario::spend($book, LedgerScenario::GROCERIES_OTHER, 2_000, '2026-11-02', null, 'Marché');
    LedgerScenario::spend($book, LedgerScenario::RESTAURANTS, 4_000, '2026-11-02');
    LedgerScenario::spend($book, BudgetScenario::PETROL, 8_000, '2026-11-02');
    LedgerScenario::spend($book, 'transport.public.bus', 16_000, '2026-11-02');

    $data = BudgetScenario::read($user, $book, '2026-11');

    expect(array_column($data['lines'], 'actual', 'category'))->toBe(['food.groceries' => 3_000, 'transport' => 24_000])
        ->and($data['expenses']['off_plan'])->toBe(4_000);
});

it('REQ-3.3 never counts income or saving entries towards an expense line', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => 10_000], income: 500_000, saving: 100_000);
    LedgerScenario::spend($book, LedgerScenario::SALARY, 500_000, '2026-11-02');
    LedgerScenario::spend($book, LedgerScenario::EMERGENCY_FUND, 100_000, '2026-11-02');

    $data = BudgetScenario::read($user, $book, '2026-11');

    expect($data['lines'][0]['actual'])->toBe(0)
        ->and($data['expenses'])->toBe(['planned' => 10_000, 'actual' => 0, 'off_plan' => 0, 'remaining' => 10_000]);
});

it('REQ-6.2 keeps actual amounts exact near the largest amount', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => 999_999_999_999]);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 999_999_999_999, '2026-11-01');
    LedgerScenario::spend($book, LedgerScenario::RESTAURANTS, 999_999_999_999, '2026-11-02');

    expect(BudgetScenario::read($user, $book, '2026-11')['lines'][0])->toMatchArray([
        'actual' => 1_999_999_999_998,
        'percent' => 200,
        'status' => 'over',
    ]);
});
