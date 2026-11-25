<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Models\BudgetPlanLine;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\CategoryLevel;
use App\Modules\Ledger\Models\CategoryType;
use App\Modules\Ledger\Support\BudgetMonth;
use App\Modules\Ledger\Support\FieldError;
use App\Modules\Ledger\Support\NewBudgetPlan;
use App\Modules\Ledger\Support\PlanLineInput;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class SaveBudgetPlan
{
    private const ATTEMPTS = 3;

    public function handle(Book $book, User $user, NewBudgetPlan $new): void
    {
        DB::transaction(function () use ($book, $user, $new): void {
            $plan = $this->lockedPlan($book, $new->month);
            $kept = $plan === null ? [] : $plan->lines()->pluck('category_id')->all();
            $lines = $this->checkedLines($new->lines, $kept);

            $plan ??= $this->createPlan($book, $user, $new->month);
            $plan->update([
                'expected_income' => $new->expectedIncome,
                'planned_saving' => $new->plannedSaving,
                'updated_by' => $user->id,
            ]);

            $plan->lines()->delete();
            BudgetPlanLine::query()->insert(array_map(fn (array $line): array => [
                'id' => (new BudgetPlanLine)->newUniqueId(),
                'budget_plan_id' => $plan->id,
                'category_id' => $line['category']->id,
                'planned' => $line['planned'],
            ], $lines));
        }, self::ATTEMPTS);
    }

    private function lockedPlan(Book $book, BudgetMonth $month): ?BudgetPlan
    {
        return BudgetPlan::of($book, $month)->lockForUpdate()->first();
    }

    private function createPlan(Book $book, User $user, BudgetMonth $month): BudgetPlan
    {
        try {
            return BudgetPlan::query()->create([
                'book_id' => $book->id,
                'month' => $month->first,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        } catch (UniqueConstraintViolationException $race) {
            return $this->lockedPlan($book, $month) ?? throw $race;
        }
    }

    /**
     * @param  list<PlanLineInput>  $lines
     * @param  list<mixed>  $kept
     * @return list<array{category: Category, planned: int}>
     */
    private function checkedLines(array $lines, array $kept): array
    {
        $categories = Category::query()
            ->whereIn('code', array_map(fn (PlanLineInput $line): string => $line->category, $lines))
            ->get()
            ->keyBy('code');
        $planned = $categories->pluck('id')->all();
        $errors = [];
        $checked = [];

        foreach ($lines as $index => $line) {
            $category = $categories->get($line->category);
            $error = $this->errorOf($category, $planned, $kept);

            if ($error !== null) {
                $errors["lines.{$index}.category"] = "lines.category.{$error}";
            } elseif ($category !== null) {
                $checked[] = ['category' => $category, 'planned' => $line->planned];
            }
        }

        if ($errors !== []) {
            throw FieldError::each($errors);
        }

        return $checked;
    }

    /**
     * @param  list<mixed>  $planned
     * @param  list<mixed>  $kept
     */
    private function errorOf(?Category $category, array $planned, array $kept): ?string
    {
        return match (true) {
            $category === null => 'not_found',
            $category->level === CategoryLevel::Leaf => 'not_plannable',
            $category->type !== CategoryType::Expense => 'not_expense',
            $category->parent_id !== null && in_array($category->parent_id, $planned, true) => 'overlaps',
            $category->isRetired() && ! in_array($category->id, $kept, true) => 'retired',
            default => null,
        };
    }
}
