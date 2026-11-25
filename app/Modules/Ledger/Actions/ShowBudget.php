<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Models\BudgetPlanLine;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\CategoryType;
use App\Modules\Ledger\Support\BookDay;
use App\Modules\Ledger\Support\Budget;
use App\Modules\Ledger\Support\BudgetLine;
use App\Modules\Ledger\Support\BudgetMonth;
use App\Modules\Ledger\Support\LeafTotal;
use App\Modules\Ledger\Support\TypeTotals;
use Illuminate\Support\Collection;

final class ShowBudget
{
    private const TODAY_INCLUDED = 1;

    public function __construct(private readonly SumByLeaf $sumByLeaf) {}

    public function handle(Book $book, BudgetMonth $month): Budget
    {
        $plan = BudgetPlan::of($book, $month)->with('lines')->first();
        $leaves = $this->sumByLeaf->handle($book, $month->period());
        $totals = array_reduce(
            $leaves,
            fn (TypeTotals $sum, LeafTotal $leaf): TypeTotals => $sum->plus($leaf->type, $leaf->total, $leaf->entries),
            TypeTotals::none(),
        );
        $daysLeft = $this->daysLeft($book, $month);

        if ($plan === null || $plan->isEmpty()) {
            return new Budget(
                month: $month,
                currency: $book->currency,
                hasPlan: false,
                daysLeft: $daysLeft,
                expectedIncome: null,
                actualIncome: $totals->income,
                plannedSaving: null,
                actualSaving: $totals->saving,
                plannedExpenses: null,
                actualExpenses: $totals->expense,
                offPlan: null,
                remaining: null,
                lines: [],
            );
        }

        $lines = $this->lines($plan, $leaves);
        $offPlan = $totals->expense - array_sum(array_map(fn (BudgetLine $line): int => $line->actual, $lines));
        $left = array_sum(array_map(fn (BudgetLine $line): int => $line->left(), $lines));

        return new Budget(
            month: $month,
            currency: $book->currency,
            hasPlan: true,
            daysLeft: $daysLeft,
            expectedIncome: $plan->expected_income,
            actualIncome: $totals->income,
            plannedSaving: $plan->planned_saving,
            actualSaving: $totals->saving,
            plannedExpenses: array_sum(array_map(fn (BudgetLine $line): int => $line->planned, $lines)),
            actualExpenses: $totals->expense,
            offPlan: $offPlan,
            remaining: max($left - $offPlan, 0),
            lines: $lines,
        );
    }

    /**
     * @param  list<LeafTotal>  $leaves
     * @return list<BudgetLine>
     */
    private function lines(BudgetPlan $plan, array $leaves): array
    {
        $categories = Category::query()->get()->keyBy('id');
        $spent = $this->expensesUnderEachCategory($leaves, $categories);
        $planLines = $plan->lines->all();

        usort($planLines, fn (BudgetPlanLine $a, BudgetPlanLine $b): int => $this->treeOrder($a, $categories) <=> $this->treeOrder($b, $categories));

        return array_map(function (BudgetPlanLine $line) use ($categories, $spent): BudgetLine {
            $category = $categories[$line->category_id];

            return BudgetLine::of(
                category: $category->code,
                planned: $line->planned,
                actual: $spent[$line->category_id] ?? 0,
                retired: $category->isRetired(),
            );
        }, $planLines);
    }

    /**
     * @param  list<LeafTotal>  $leaves
     * @param  Collection<string, Category>  $categories
     * @return array<string, int>
     */
    private function expensesUnderEachCategory(array $leaves, Collection $categories): array
    {
        $spent = [];

        foreach ($leaves as $leaf) {
            if ($leaf->type !== CategoryType::Expense) {
                continue;
            }

            $category = $categories->get($leaf->categoryId);

            while ($category !== null) {
                $spent[$category->id] = ($spent[$category->id] ?? 0) + $leaf->total;
                $category = $category->parent_id === null ? null : $categories->get($category->parent_id);
            }
        }

        return $spent;
    }

    /**
     * @param  Collection<string, Category>  $categories
     * @return list<int|string>
     */
    private function treeOrder(BudgetPlanLine $line, Collection $categories): array
    {
        $category = $categories->get($line->category_id);

        if ($category === null) {
            return [];
        }

        $top = $category->parent_id === null ? $category : $categories->get($category->parent_id);

        if ($top === null || $top->id === $category->id) {
            return [$category->position, $category->code, 0, 0, ''];
        }

        return [$top->position, $top->code, 1, $category->position, $category->code];
    }

    private function daysLeft(Book $book, BudgetMonth $month): ?int
    {
        $today = BookDay::today($book);

        if ($today < $month->first || $today > $month->last) {
            return null;
        }

        return (int) BookDay::parse($today)->diffInDays(BookDay::parse($month->last)) + self::TODAY_INCLUDED;
    }
}
