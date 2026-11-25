<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-3.1 and REQ-3.2 answer the month\'s plan next to what was spent', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::leaf('home.housing.rent');
    LedgerScenario::leaf('entertainment.outings.cinema');
    Category::query()->where('code', 'food')->update(['position' => 0]);
    Category::query()->where('code', 'home')->update(['position' => 1]);
    Category::query()->where('code', 'transport')->update(['position' => 2]);
    Category::query()->where('code', 'entertainment')->update(['position' => 3]);
    BudgetScenario::plan($book, '2026-11', [
        'entertainment.outings' => 0,
        'transport' => 100_000,
        'home.housing' => 400_000,
        'food' => 600_000,
    ], income: 2_500_000, saving: 300_000);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 540_000, '2026-11-03');
    LedgerScenario::spend($book, 'home.housing.rent', 400_000, '2026-11-01');
    LedgerScenario::spend($book, BudgetScenario::PETROL, 160_000, '2026-11-12');
    LedgerScenario::spend($book, 'entertainment.outings.cinema', 100_000, '2026-11-20');
    LedgerScenario::spend($book, BudgetScenario::MEDICINE, 12_500, '2026-11-21');
    LedgerScenario::spend($book, LedgerScenario::SALARY, 2_500_000, '2026-11-01');
    LedgerScenario::spend($book, LedgerScenario::EMERGENCY_FUND, 100_000, '2026-11-02');

    getJson(BudgetScenario::url($book, '2026-11'), HouseholdScenario::as($user))
        ->assertOk()
        ->assertExactJson(['data' => [
            'month' => '2026-11',
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'currency' => 'TND',
            'has_plan' => true,
            'days_left' => 6,
            'income' => ['expected' => 2_500_000, 'actual' => 2_500_000],
            'saving' => ['planned' => 300_000, 'actual' => 100_000],
            'expenses' => ['planned' => 1_100_000, 'actual' => 1_212_500, 'off_plan' => 12_500, 'remaining' => 47_500],
            'lines' => [
                ['category' => 'food', 'planned' => 600_000, 'actual' => 540_000, 'percent' => 90, 'status' => 'warning', 'retired' => false],
                ['category' => 'home.housing', 'planned' => 400_000, 'actual' => 400_000, 'percent' => 100, 'status' => 'warning', 'retired' => false],
                ['category' => 'transport', 'planned' => 100_000, 'actual' => 160_000, 'percent' => 160, 'status' => 'over', 'retired' => false],
                ['category' => 'entertainment.outings', 'planned' => 0, 'actual' => 100_000, 'percent' => null, 'status' => 'over', 'retired' => false],
            ],
        ]]);
});

it('REQ-3.2 orders lines as the category tree: tops by position, each followed by its groups', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    Category::query()->where('code', 'transport')->update(['position' => 0]);
    Category::query()->where('code', 'food')->update(['position' => 1]);
    Category::query()->where('code', 'health')->update(['position' => 2]);
    Category::query()->where('code', 'food.groceries')->update(['position' => 1]);
    Category::query()->where('code', 'food.eating_out')->update(['position' => 0]);
    BudgetScenario::plan($book, '2026-11', ['health' => 1, 'food.groceries' => 1, 'transport.fuel' => 1, 'food.eating_out' => 1]);

    expect(array_column(BudgetScenario::read($user, $book, '2026-11')['lines'], 'category'))
        ->toBe(['transport.fuel', 'food.eating_out', 'food.groceries', 'health']);
});

it('REQ-3.6 answers a month without a plan with has_plan false and nulls, actuals still sent', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user, 'Africa/Tunis', 'EUR');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_250, '2026-11-03');
    LedgerScenario::spend($book, LedgerScenario::SALARY, 300_000, '2026-11-01');

    expect(BudgetScenario::read($user, $book, '2026-11'))->toBe([
        'month' => '2026-11',
        'from' => '2026-11-01',
        'to' => '2026-11-30',
        'currency' => 'EUR',
        'has_plan' => false,
        'days_left' => 6,
        'income' => ['expected' => null, 'actual' => 300_000],
        'saving' => ['planned' => null, 'actual' => 0],
        'expenses' => ['planned' => null, 'actual' => 1_250, 'off_plan' => null, 'remaining' => null],
        'lines' => [],
    ]);
});

it('REQ-3.6 treats a cleared plan as no plan', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11');

    expect(BudgetScenario::read($user, $book, '2026-11'))->toMatchArray([
        'has_plan' => false,
        'lines' => [],
        'income' => ['expected' => null, 'actual' => 0],
    ]);
});

it('REQ-3.5 gives the expected income and the planned saving next to their actuals, even without lines', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', income: 2_000_000, saving: 0);
    LedgerScenario::spend($book, LedgerScenario::SALARY, 1_800_000, '2026-11-01');
    LedgerScenario::spend($book, LedgerScenario::EMERGENCY_FUND, 50_000, '2026-11-01');
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 30_000, '2026-11-01');

    expect(BudgetScenario::read($user, $book, '2026-11'))->toMatchArray([
        'has_plan' => true,
        'income' => ['expected' => 2_000_000, 'actual' => 1_800_000],
        'saving' => ['planned' => 0, 'actual' => 50_000],
        'expenses' => ['planned' => 0, 'actual' => 30_000, 'off_plan' => 30_000, 'remaining' => 0],
        'lines' => [],
    ]);
});

it('REQ-6.1 reads a budget with the same number of queries whatever the number of entries', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => 100_000, 'transport' => 50_000]);
    $headers = HouseholdScenario::as($user);
    $count = function () use ($book, $headers): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        getJson(BudgetScenario::url($book, '2026-11'), $headers)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-01');
    $count();
    $few = $count();

    foreach (range(2, 28) as $day) {
        LedgerScenario::spend($book, LedgerScenario::RESTAURANTS, 1_000, sprintf('2026-11-%02d', $day));
        LedgerScenario::spend($book, BudgetScenario::PETROL, 1_000, sprintf('2026-11-%02d', $day));
        LedgerScenario::spend($book, BudgetScenario::MEDICINE, 1_000, sprintf('2026-11-%02d', $day));
    }

    expect($count())->toBe($few);
});
