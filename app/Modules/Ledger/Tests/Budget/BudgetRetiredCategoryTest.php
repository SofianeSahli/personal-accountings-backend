<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-3.8 keeps a line on a retired category, flagged, and counts its entries', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food.groceries' => 10_000, 'transport' => 5_000]);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 9_000, '2026-11-02');
    Category::query()->whereIn('code', ['food.groceries', LedgerScenario::SUPERMARKET])->update(['retired_at' => now()]);

    expect(BudgetScenario::read($user, $book, '2026-11')['lines'])->toBe([
        ['category' => 'food.groceries', 'planned' => 10_000, 'actual' => 9_000, 'percent' => 90, 'status' => 'warning', 'retired' => true],
        ['category' => 'transport', 'planned' => 5_000, 'actual' => 0, 'percent' => 0, 'status' => 'ok', 'retired' => false],
    ]);
});
