<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

use function Pest\Laravel\call;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-5.1 copies the previous month\'s lines, expected income and planned saving', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();
    BudgetScenario::plan($book, '2026-10', ['food' => 600_000, 'transport.fuel' => 100_000], income: 2_500_000, saving: 300_000);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 50_000, '2026-11-02');

    BudgetScenario::copy($member, $book, '2026-11')
        ->assertOk()
        ->assertJsonPath('data.month', '2026-11')
        ->assertJsonPath('data.has_plan', true)
        ->assertJsonPath('data.income.expected', 2_500_000)
        ->assertJsonPath('data.saving.planned', 300_000)
        ->assertJsonPath('data.lines.0', ['category' => 'food', 'planned' => 600_000, 'actual' => 50_000, 'percent' => 8, 'status' => 'ok', 'retired' => false]);

    expect(BudgetScenario::linesOf($book, '2026-11'))->toBe([
        ['category' => 'food', 'planned' => 600_000],
        ['category' => 'transport.fuel', 'planned' => 100_000],
    ])
        ->and(BudgetScenario::linesOf($book, '2026-10'))->toHaveCount(2)
        ->and(BudgetPlan::query()->where('month', '2026-11-01')->sole()->created_by)->toBe($member->id);
});

it('REQ-5.1 copies December of the year before into January', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-12', ['food' => 1_000]);

    BudgetScenario::copy($user, $book, '2027-01')->assertOk()->assertJsonPath('data.lines.0.category', 'food');
});

it('REQ-5.4 skips the lines on categories retired since', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-10', ['food' => 1_000, 'transport' => 2_000]);
    Category::query()->where('code', 'transport')->update(['retired_at' => now()]);

    BudgetScenario::copy($user, $book, '2026-11')->assertOk();

    expect(BudgetScenario::linesOf($book, '2026-11'))->toBe([['category' => 'food', 'planned' => 1_000]]);
});

it('REQ-5.2 refuses to copy over a month that has a plan, changing nothing', function (?bool $replace): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-10', ['food' => 1_000]);
    BudgetScenario::plan($book, '2026-11', ['transport' => 2_000]);

    BudgetScenario::copy($user, $book, '2026-11', $replace === null ? [] : ['replace' => $replace])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'budget.not_empty']]);

    expect(BudgetScenario::linesOf($book, '2026-11'))->toBe([['category' => 'transport', 'planned' => 2_000]]);
})->with(['no replace' => null, 'replace false' => false]);

it('REQ-5.2 replaces the month\'s plan when asked to', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-10', ['food' => 1_000]);
    BudgetScenario::plan($book, '2026-11', ['transport' => 2_000], income: 5);

    BudgetScenario::copy($user, $book, '2026-11', ['replace' => true])
        ->assertOk()
        ->assertJsonPath('data.income.expected', null);

    expect(BudgetScenario::linesOf($book, '2026-11'))->toBe([['category' => 'food', 'planned' => 1_000]]);
});

it('REQ-5.2 copies into a month whose plan was cleared, without replace', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-10', ['food' => 1_000]);
    BudgetScenario::plan($book, '2026-11');

    BudgetScenario::copy($user, $book, '2026-11')->assertOk()->assertJsonPath('data.has_plan', true);
});

it('REQ-5.3 refuses to copy when the previous month has no plan, changing nothing', function (bool $cleared): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan(LedgerScenario::personalBook($user), '2026-10', ['food' => 1_000]);

    if ($cleared) {
        BudgetScenario::plan($book, '2026-10');
    }

    BudgetScenario::copy($user, $book, '2026-11', ['replace' => true])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'budget.nothing_to_copy']]);

    expect(BudgetPlan::query()->where('book_id', $book->id)->where('month', '2026-11-01')->exists())->toBeFalse();
})->with(['no plan row' => false, 'a cleared plan' => true]);

it('REQ-5.1 accepts an empty body', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-10', ['food' => 1_000]);

    call('POST', BudgetScenario::url($book, '2026-11', '/copy'), [], [], [], [
        ...array_combine(
            array_map(fn (string $name): string => 'HTTP_'.strtoupper(str_replace('-', '_', $name)), array_keys(HouseholdScenario::as($user))),
            array_values(HouseholdScenario::as($user)),
        ),
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ])->assertOk();
});

it('REQ-5.5 refuses a replace that is not a boolean', function (mixed $replace): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-10', ['food' => 1_000]);

    BudgetScenario::copy($user, $book, '2026-11', ['replace' => $replace])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['replace' => ['replace.boolean']]]]);
})->with(['a string' => 'yes', 'a numeric string' => '1', 'an integer' => 1, 'an array' => [[true]]]);
