<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\TransactionView;

final class ShowTransaction
{
    public function __construct(private readonly NameAccounts $nameAccounts) {}

    public function handle(Transaction $transaction): TransactionView
    {
        $transaction->loadMissing('category');

        return new TransactionView($transaction, $this->nameAccounts->handle([$transaction]));
    }
}
