<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    LedgerScenario::categories();
});

it('REQ-1.5 and REQ-4.1 return the totals, the balance, the days and the months of the period', function (): void {
    $user = User::factory()->create(['name' => 'Sofiane']);
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::spend($book, LedgerScenario::SALARY, 2_500_000, '2026-11-01', $user);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 850_000, '2026-11-01', $user);
    LedgerScenario::spend($book, LedgerScenario::RESTAURANTS, 62_500, '2026-11-15', $user);
    LedgerScenario::spend($book, LedgerScenario::EMERGENCY_FUND, 300_000, '2026-11-20', $user);

    $data = getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), HouseholdScenario::as($user))
        ->assertOk()
        ->json('data');

    expect($data['from'])->toBe('2026-11-01')
        ->and($data['to'])->toBe('2026-11-30')
        ->and($data['currency'])->toBe('TND')
        ->and($data['totals'])->toBe(['income' => 2_500_000, 'expense' => 912_500, 'saving' => 300_000, 'balance' => 1_287_500, 'entries' => 4])
        ->and($data['days'])->toBe([
            ['date' => '2026-11-01', 'income' => 2_500_000, 'expense' => 850_000, 'saving' => 0, 'balance' => 1_650_000, 'entries' => 2],
            ['date' => '2026-11-15', 'income' => 0, 'expense' => 62_500, 'saving' => 0, 'balance' => -62_500, 'entries' => 1],
            ['date' => '2026-11-20', 'income' => 0, 'expense' => 0, 'saving' => 300_000, 'balance' => -300_000, 'entries' => 1],
        ])
        ->and($data['months'])->toBe([
            ['month' => '2026-11', 'income' => 2_500_000, 'expense' => 912_500, 'saving' => 300_000, 'balance' => 1_287_500, 'entries' => 4],
        ]);
});

it('REQ-1.6 counts only the live entries of this book dated in the period', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-01');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 2_000, '2026-11-30');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 40_000, '2026-10-31');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 80_000, '2026-12-01');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 160_000, '2026-11-10')->delete();
    LedgerScenario::spend(Book::factory()->create(), LedgerScenario::SUPERMARKET, 320_000, '2026-11-10');
    LedgerScenario::spend(LedgerScenario::personalBook($user), LedgerScenario::SUPERMARKET, 640_000, '2026-11-10');

    getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.totals', ['income' => 0, 'expense' => 3_000, 'saving' => 0, 'balance' => -3_000, 'entries' => 2]);
});

it('REQ-1.7 answers zero totals and empty lists for a period without entries', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user, 'UTC', 'EUR');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-10-31');

    getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), HouseholdScenario::as($user))
        ->assertOk()
        ->assertExactJson(['data' => [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'currency' => 'EUR',
            'totals' => ['income' => 0, 'expense' => 0, 'saving' => 0, 'balance' => 0, 'entries' => 0],
            'categories' => [],
            'members' => [],
            'days' => [],
            'months' => [],
        ]]);
});

it('REQ-4.2 limits each month line to its days inside the range, oldest first', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    foreach (['2026-10-24', '2026-10-25', '2026-10-31', '2026-11-01', '2026-11-24', '2026-11-25'] as $day) {
        LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, $day);
    }

    $data = getJson(LedgerScenario::summaryUrl($book, 'from=2026-10-25&to=2026-11-24'), HouseholdScenario::as($user))
        ->assertOk()
        ->json('data');

    expect(array_column($data['days'], 'date'))->toBe(['2026-10-25', '2026-10-31', '2026-11-01', '2026-11-24'])
        ->and(array_column($data['months'], 'month'))->toBe(['2026-10', '2026-11'])
        ->and(array_column($data['months'], 'expense'))->toBe([2_000, 2_000])
        ->and($data['totals']['entries'])->toBe(4);
});

it('REQ-5.1 runs the same number of queries whatever the number of entries', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $headers = HouseholdScenario::as($user);
    $count = function () use ($book, $headers): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), $headers)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-01', $user);
    $count();
    $few = $count();

    foreach (range(2, 28) as $day) {
        LedgerScenario::spend($book, LedgerScenario::RESTAURANTS, 1_000, sprintf('2026-11-%02d', $day), User::factory()->create());
        LedgerScenario::spend($book, LedgerScenario::GROCERIES_OTHER, 1_000, sprintf('2026-11-%02d', $day), null, "Nom {$day}");
    }

    expect($count())->toBe($few);
});

it('REQ-5.2 keeps amounts as exact integers near the largest amount', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::spend($book, LedgerScenario::SALARY, 999_999_999_999, '2026-11-01');
    LedgerScenario::spend($book, LedgerScenario::SALARY, 999_999_999_999, '2026-11-02');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1, '2026-11-02');

    getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonPath('data.totals.income', 1_999_999_999_998)
        ->assertJsonPath('data.totals.balance', 1_999_999_999_997);
});
