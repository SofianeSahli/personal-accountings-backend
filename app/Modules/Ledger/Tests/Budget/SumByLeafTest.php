<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Actions\SumByLeaf;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\CategoryType;
use App\Modules\Ledger\Support\LeafTotal;
use App\Modules\Ledger\Support\SummaryPeriod;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

beforeEach(function (): void {
    LedgerScenario::categories();
});

/**
 * @return array<string, array{type: string, total: int, entries: int}>
 */
function sumByLeaf(Book $book, string $month): array
{
    $sums = [];

    foreach (app(SumByLeaf::class)->handle($book, SummaryPeriod::month($month)) as $leaf) {
        expect($leaf)->toBeInstanceOf(LeafTotal::class);
        $sums[LedgerScenario::codeOf($leaf->categoryId)] = ['type' => $leaf->type->value, 'total' => $leaf->total, 'entries' => $leaf->entries];
    }

    ksort($sums);

    return $sums;
}

it('REQ-6.1 sums the live entries of the book in the period, per leaf, with its type', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-01');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 2_500, '2026-11-30');
    LedgerScenario::spend($book, LedgerScenario::SALARY, 999_999_999_999, '2026-11-15');
    LedgerScenario::spend($book, LedgerScenario::EMERGENCY_FUND, 300, '2026-11-15');
    LedgerScenario::spend($book, LedgerScenario::RESTAURANTS, 7_000, '2026-11-10')->delete();
    LedgerScenario::spend($book, LedgerScenario::RESTAURANTS, 8_000, '2026-10-31');
    LedgerScenario::spend($book, LedgerScenario::RESTAURANTS, 9_000, '2026-12-01');
    LedgerScenario::spend(LedgerScenario::personalBook($user), LedgerScenario::RESTAURANTS, 10_000, '2026-11-10');

    expect(sumByLeaf($book, '2026-11'))->toBe([
        LedgerScenario::SUPERMARKET => ['type' => CategoryType::Expense->value, 'total' => 3_500, 'entries' => 2],
        LedgerScenario::SALARY => ['type' => CategoryType::Income->value, 'total' => 999_999_999_999, 'entries' => 1],
        LedgerScenario::EMERGENCY_FUND => ['type' => CategoryType::Saving->value, 'total' => 300, 'entries' => 1],
    ]);
});

it('REQ-6.1 returns nothing for a period without entries', function (): void {
    expect(sumByLeaf(LedgerScenario::personalBook(User::factory()->create()), '2026-11'))->toBe([]);
});
