<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Actions\CopyBudgetPlan;
use App\Modules\Ledger\Actions\SaveBudgetPlan;
use App\Modules\Ledger\Actions\ShowBudget;
use App\Modules\Ledger\Controllers\Requests\CopyBudgetRequest;
use App\Modules\Ledger\Controllers\Requests\SaveBudgetRequest;
use App\Modules\Ledger\Controllers\Requests\ShowBudgetRequest;
use App\Modules\Ledger\Controllers\Resources\BudgetResource;
use App\Modules\Ledger\Support\CurrentBook;
use Illuminate\Container\Attributes\CurrentUser;

final class BudgetController
{
    public function show(ShowBudgetRequest $request, CurrentBook $current, ShowBudget $showBudget): BudgetResource
    {
        return new BudgetResource($showBudget->handle($current->book, $request->month()));
    }

    public function update(
        SaveBudgetRequest $request,
        CurrentBook $current,
        #[CurrentUser] User $user,
        SaveBudgetPlan $saveBudgetPlan,
        ShowBudget $showBudget,
    ): BudgetResource {
        $saveBudgetPlan->handle($current->book, $user, $request->newBudgetPlan());

        return new BudgetResource($showBudget->handle($current->book, $request->month()));
    }

    public function copy(
        CopyBudgetRequest $request,
        CurrentBook $current,
        #[CurrentUser] User $user,
        CopyBudgetPlan $copyBudgetPlan,
        ShowBudget $showBudget,
    ): BudgetResource {
        $copyBudgetPlan->handle($current->book, $user, $request->month(), $request->replaces());

        return new BudgetResource($showBudget->handle($current->book, $request->month()));
    }
}
