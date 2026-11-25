<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Models\BudgetPlanLine;
use App\Modules\Ledger\Support\BudgetMonth;
use App\Modules\Ledger\Support\NewBudgetPlan;
use App\Modules\Ledger\Support\PlanLineInput;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class CopyBudgetPlan
{
    public function __construct(private readonly SaveBudgetPlan $saveBudgetPlan) {}

    public function handle(Book $book, User $user, BudgetMonth $month, bool $replace): void
    {
        DB::transaction(function () use ($book, $user, $month, $replace): void {
            $previous = BudgetPlan::of($book, $month->previous())->with('lines.category')->first();

            if ($previous === null || $previous->isEmpty()) {
                throw new ApiException('budget.nothing_to_copy', Response::HTTP_CONFLICT);
            }

            $target = BudgetPlan::of($book, $month)->with('lines')->lockForUpdate()->first();

            if (! $replace && $target !== null && ! $target->isEmpty()) {
                throw new ApiException('budget.not_empty', Response::HTTP_CONFLICT);
            }

            $lines = $previous->lines
                ->reject(fn (BudgetPlanLine $line): bool => $line->category->isRetired())
                ->map(fn (BudgetPlanLine $line): PlanLineInput => new PlanLineInput($line->category->code, $line->planned))
                ->values()
                ->all();

            $this->saveBudgetPlan->handle(
                $book,
                $user,
                new NewBudgetPlan($month, $previous->expected_income, $previous->planned_saving, $lines),
            );
        });
    }
}
