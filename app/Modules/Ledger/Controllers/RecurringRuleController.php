<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Actions\CreateRecurringRule;
use App\Modules\Ledger\Actions\DeleteRecurringRule;
use App\Modules\Ledger\Actions\ListRecurringRules;
use App\Modules\Ledger\Actions\ShowRecurringRule;
use App\Modules\Ledger\Actions\UpdateRecurringRule;
use App\Modules\Ledger\Controllers\Requests\CreateRecurringRuleRequest;
use App\Modules\Ledger\Controllers\Requests\UpdateRecurringRuleRequest;
use App\Modules\Ledger\Controllers\Resources\RecurringRuleList;
use App\Modules\Ledger\Controllers\Resources\RecurringRuleResource;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Support\CurrentBook;
use App\Modules\Ledger\Support\RecurringRuleView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class RecurringRuleController
{
    public function index(CurrentBook $current, ListRecurringRules $listRecurringRules): RecurringRuleList
    {
        return new RecurringRuleList($listRecurringRules->handle($current->book), $current->book->currency);
    }

    public function store(
        CreateRecurringRuleRequest $request,
        CurrentBook $current,
        #[CurrentUser] User $user,
        CreateRecurringRule $createRecurringRule,
    ): JsonResponse {
        $view = $createRecurringRule->handle($current->book, $user, $request->newRule());

        return $this->resource($view, $current)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(CurrentBook $current, RecurringRule $recurringRule, ShowRecurringRule $showRecurringRule): RecurringRuleResource
    {
        return $this->resource($showRecurringRule->handle($recurringRule), $current);
    }

    public function update(
        UpdateRecurringRuleRequest $request,
        CurrentBook $current,
        #[CurrentUser] User $user,
        RecurringRule $recurringRule,
        UpdateRecurringRule $updateRecurringRule,
    ): RecurringRuleResource {
        $view = $updateRecurringRule->handle($current->book, $recurringRule, $user, $request->changes());

        return $this->resource($view, $current);
    }

    public function destroy(RecurringRule $recurringRule, DeleteRecurringRule $deleteRecurringRule): Response
    {
        $deleteRecurringRule->handle($recurringRule);

        return response()->noContent();
    }

    private function resource(RecurringRuleView $view, CurrentBook $current): RecurringRuleResource
    {
        return new RecurringRuleResource($view->rule, $current->book->currency, $view->names);
    }
}
