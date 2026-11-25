<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Models\BudgetPlanLine;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Support\BudgetMonth;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * @return array<string, array{type: string, collation: ?string}>
 */
function budgetColumns(string $table): array
{
    $columns = [];

    foreach (DB::select("SHOW FULL COLUMNS FROM `{$table}`") as $column) {
        $columns[(string) $column->Field] = [
            'type' => strtolower((string) $column->Type),
            'collation' => $column->Collation === null ? null : (string) $column->Collation,
        ];
    }

    return $columns;
}

it('REQ-2.10 refuses a second plan for the same book and month at the database level', function (): void {
    $plan = BudgetPlan::factory()->forMonth('2026-11')->create();

    expect(fn () => BudgetPlan::factory()->inBook(Book::query()->findOrFail($plan->book_id))->forMonth('2026-11')->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('REQ-2.1 gives plans and lines ascii_bin uuid keys and bigint amounts', function (): void {
    $uuid = ['type' => 'char(36)', 'collation' => 'ascii_bin'];

    expect(budgetColumns('budget_plans'))->toMatchArray([
        'id' => $uuid,
        'book_id' => $uuid,
        'created_by' => $uuid,
        'updated_by' => $uuid,
        'month' => ['type' => 'date', 'collation' => null],
        'expected_income' => ['type' => 'bigint unsigned', 'collation' => null],
        'planned_saving' => ['type' => 'bigint unsigned', 'collation' => null],
    ])->and(budgetColumns('budget_plan_lines'))->toMatchArray([
        'id' => $uuid,
        'budget_plan_id' => $uuid,
        'category_id' => $uuid,
        'planned' => ['type' => 'bigint unsigned', 'collation' => null],
    ]);
});

it('REQ-0.4 reaches a book\'s plan only through its book id', function (): void {
    $mine = Book::factory()->create();
    $theirs = Book::factory()->create();
    $plan = BudgetPlan::factory()->inBook($mine)->forMonth('2026-11')->create(['expected_income' => 1_000]);
    BudgetPlan::factory()->inBook($theirs)->forMonth('2026-11')->create(['expected_income' => 2_000]);

    expect(BudgetPlan::of($mine, BudgetMonth::parse('2026-11'))->sole()->id)->toBe($plan->id)
        ->and(BudgetPlan::of($mine, BudgetMonth::parse('2026-12'))->exists())->toBeFalse();
});

it('REQ-2.5 refuses the same category twice in one plan at the database level', function (): void {
    $line = BudgetPlanLine::factory()->create();

    expect(fn () => BudgetPlanLine::factory()->create(['budget_plan_id' => $line->budget_plan_id, 'category_id' => $line->category_id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('REQ-2.1 never cascades a delete to plans or lines', function (string $table): void {
    $line = BudgetPlanLine::factory()->create();
    $plan = BudgetPlan::query()->findOrFail($line->budget_plan_id);
    $ids = [
        'books' => $plan->book_id,
        'users' => $plan->created_by,
        'categories' => $line->category_id,
        'budget_plans' => $plan->id,
    ];

    expect(fn () => DB::table($table)->where('id', $ids[$table])->delete())->toThrow(QueryException::class);
    expect(BudgetPlanLine::query()->whereKey($line->id)->exists())->toBeTrue();
})->with(['books', 'users', 'categories', 'budget_plans']);

it('REQ-2.1 links lines to their plan and category', function (): void {
    $category = Category::factory()->create();
    $plan = BudgetPlan::factory()->create(['created_by' => User::factory()->create()->id]);
    BudgetPlanLine::factory()->inPlan($plan)->on($category, 600_000)->create();

    $line = $plan->lines()->sole();

    expect($line->planned)->toBe(600_000)
        ->and($line->category->id)->toBe($category->id)
        ->and($line->plan->id)->toBe($plan->id);
});
