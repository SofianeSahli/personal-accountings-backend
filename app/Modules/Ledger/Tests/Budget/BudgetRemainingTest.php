<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-4.2 never lets an overspent line offset another', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => 100_000, 'transport' => 100_000]);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 150_000, '2026-11-02');
    LedgerScenario::spend($book, BudgetScenario::PETROL, 40_000, '2026-11-02');

    expect(BudgetScenario::read($user, $book, '2026-11')['expenses'])->toBe([
        'planned' => 200_000,
        'actual' => 190_000,
        'off_plan' => 0,
        'remaining' => 60_000,
    ]);
});

it('REQ-4.1 and REQ-4.2 show spending outside the plan and subtract it', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => 100_000, 'transport' => 100_000]);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 150_000, '2026-11-02');
    LedgerScenario::spend($book, BudgetScenario::PETROL, 40_000, '2026-11-02');
    LedgerScenario::spend($book, BudgetScenario::MEDICINE, 25_000, '2026-11-02');

    expect(BudgetScenario::read($user, $book, '2026-11')['expenses'])->toBe([
        'planned' => 200_000,
        'actual' => 215_000,
        'off_plan' => 25_000,
        'remaining' => 35_000,
    ]);
});

it('REQ-4.2 never gives a remaining amount below 0', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => 100_000]);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 10_000, '2026-11-02');
    LedgerScenario::spend($book, BudgetScenario::MEDICINE, 1_000_000, '2026-11-02');

    expect(BudgetScenario::read($user, $book, '2026-11')['expenses'])->toBe([
        'planned' => 100_000,
        'actual' => 1_010_000,
        'off_plan' => 1_000_000,
        'remaining' => 0,
    ]);
});

it('REQ-3.7 answers a month without entries with zeros and every line ok', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => 100_000, 'transport' => 0], income: 900_000, saving: 50_000);

    $data = BudgetScenario::read($user, $book, '2026-11');

    expect($data['lines'])->toBe([
        ['category' => 'food', 'planned' => 100_000, 'actual' => 0, 'percent' => 0, 'status' => 'ok', 'retired' => false],
        ['category' => 'transport', 'planned' => 0, 'actual' => 0, 'percent' => null, 'status' => 'ok', 'retired' => false],
    ])
        ->and($data['expenses'])->toBe(['planned' => 100_000, 'actual' => 0, 'off_plan' => 0, 'remaining' => 100_000])
        ->and($data['income'])->toBe(['expected' => 900_000, 'actual' => 0])
        ->and($data['saving'])->toBe(['planned' => 50_000, 'actual' => 0]);
});
