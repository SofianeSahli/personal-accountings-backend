<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Actions\DeleteTransaction;
use App\Modules\Ledger\Actions\ListTransactions;
use App\Modules\Ledger\Actions\RecordTransaction;
use App\Modules\Ledger\Actions\ShowTransaction;
use App\Modules\Ledger\Actions\UpdateTransaction;
use App\Modules\Ledger\Controllers\Requests\ListTransactionsRequest;
use App\Modules\Ledger\Controllers\Requests\RecordTransactionRequest;
use App\Modules\Ledger\Controllers\Requests\UpdateTransactionRequest;
use App\Modules\Ledger\Controllers\Resources\TransactionList;
use App\Modules\Ledger\Controllers\Resources\TransactionResource;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\CurrentBook;
use App\Modules\Ledger\Support\TransactionView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class TransactionController
{
    public function index(ListTransactionsRequest $request, CurrentBook $current, ListTransactions $listTransactions): TransactionList
    {
        return new TransactionList(
            $listTransactions->handle($current->book, $request->filters()),
            $current->book->currency,
        );
    }

    public function store(
        RecordTransactionRequest $request,
        CurrentBook $current,
        #[CurrentUser] User $user,
        RecordTransaction $recordTransaction,
    ): JsonResponse {
        $recorded = $recordTransaction->handle($current->book, $user, $request->newTransaction());

        return $this->resource($recorded->view, $current)
            ->response()
            ->setStatusCode($recorded->created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function show(CurrentBook $current, Transaction $entry, ShowTransaction $showTransaction): TransactionResource
    {
        return $this->resource($showTransaction->handle($entry), $current);
    }

    public function update(
        UpdateTransactionRequest $request,
        CurrentBook $current,
        #[CurrentUser] User $user,
        Transaction $entry,
        UpdateTransaction $updateTransaction,
    ): TransactionResource {
        return $this->resource($updateTransaction->handle($entry, $user, $request->changes()), $current);
    }

    public function destroy(Transaction $entry, DeleteTransaction $deleteTransaction): Response
    {
        $deleteTransaction->handle($entry);

        return response()->noContent();
    }

    private function resource(TransactionView $view, CurrentBook $current): TransactionResource
    {
        return new TransactionResource($view->transaction, $current->book->currency, $view->names);
    }
}
