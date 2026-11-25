<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Actions\SaveBudgetPlan;
use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Support\BudgetMonth;
use App\Modules\Ledger\Support\NewBudgetPlan;
use App\Modules\Ledger\Support\PlanLineInput;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-2.1 saves a month and answers exactly what a following GET answers', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 5_000, '2026-11-03');

    $saved = BudgetScenario::save($user, $book, '2026-11', [
        'expected_income' => 2_500_000,
        'planned_saving' => 300_000,
        'lines' => [
            ['category' => 'food', 'planned' => 600_000],
            ['category' => 'transport.fuel', 'planned' => 100_000],
        ],
    ])->assertOk()->json();

    expect($saved['data'])->toMatchArray([
        'has_plan' => true,
        'income' => ['expected' => 2_500_000, 'actual' => 0],
        'saving' => ['planned' => 300_000, 'actual' => 0],
        'expenses' => ['planned' => 700_000, 'actual' => 5_000, 'off_plan' => 0, 'remaining' => 695_000],
    ])
        ->and(['data' => BudgetScenario::read($user, $book, '2026-11')])->toBe($saved);
});

it('REQ-2.1 replaces the whole month: lines, income and saving', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => 1_000, 'transport' => 2_000], income: 10, saving: 20);

    BudgetScenario::save($user, $book, '2026-11', [
        'expected_income' => null,
        'lines' => [['category' => 'health', 'planned' => 3_000], ['category' => 'food', 'planned' => 4_000]],
    ])->assertOk()
        ->assertJsonPath('data.income.expected', null)
        ->assertJsonPath('data.saving.planned', null);

    expect(BudgetScenario::linesOf($book, '2026-11'))->toBe([
        ['category' => 'food', 'planned' => 4_000],
        ['category' => 'health', 'planned' => 3_000],
    ])->and(BudgetPlan::query()->count())->toBe(1);
});

it('REQ-2.2 clears the month with an empty plan and keeps its row', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    $plan = BudgetScenario::plan($book, '2026-11', ['food' => 1_000], income: 10, saving: 20);

    BudgetScenario::save($user, $book, '2026-11', ['lines' => []])
        ->assertOk()
        ->assertJsonPath('data.has_plan', false)
        ->assertJsonPath('data.lines', [])
        ->assertJsonPath('data.expenses.planned', null);

    expect(BudgetScenario::linesOf($book, '2026-11'))->toBe([])
        ->and(BudgetPlan::query()->whereKey($plan->id)->exists())->toBeTrue();
});

it('REQ-2.1 saves a past month and a month without any plan before', function (string $month): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);

    BudgetScenario::save($user, $book, $month, ['lines' => [['category' => 'food', 'planned' => 0]]])
        ->assertOk()
        ->assertJsonPath('data.has_plan', true)
        ->assertJsonPath('data.lines.0.status', 'ok');
})->with(['2000-01', '2026-10', '2027-11']);

it('REQ-2.1 records who created the plan and who saved it last', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();

    BudgetScenario::save($owner, $book, '2026-11', ['lines' => []])->assertOk();
    BudgetScenario::save($member, $book, '2026-11', ['lines' => []])->assertOk();

    expect(BudgetPlan::query()->sole()->only(['created_by', 'updated_by']))
        ->toBe(['created_by' => $owner->id, 'updated_by' => $member->id]);
});

it('REQ-2.9 changes nothing when a save is refused', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => 1_000], income: 10);

    BudgetScenario::save($user, $book, '2026-11', [
        'expected_income' => 99,
        'lines' => [['category' => 'transport', 'planned' => 5], ['category' => LedgerScenario::SUPERMARKET, 'planned' => 5]],
    ])->assertStatus(422);

    expect(BudgetScenario::linesOf($book, '2026-11'))->toBe([['category' => 'food', 'planned' => 1_000]])
        ->and(BudgetPlan::query()->sole()->expected_income)->toBe(10);
});

it('REQ-2.9 rolls the whole save back when writing the lines fails', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-11', ['food' => 1_000], income: 10);
    DB::connection()->beforeExecuting(function (string $query): void {
        if (str_starts_with($query, 'insert into `budget_plan_lines`')) {
            throw new RuntimeException('Disk full.');
        }
    });

    expect(fn () => app(SaveBudgetPlan::class)->handle($book, $user, new NewBudgetPlan(
        BudgetMonth::parse('2026-11'),
        99,
        null,
        [new PlanLineInput('transport', 5)],
    )))->toThrow(RuntimeException::class, 'Disk full.');

    expect(BudgetScenario::linesOf($book, '2026-11'))->toBe([['category' => 'food', 'planned' => 1_000]])
        ->and(BudgetPlan::query()->sole()->expected_income)->toBe(10);
});
