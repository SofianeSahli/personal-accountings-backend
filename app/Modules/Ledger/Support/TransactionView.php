<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\Transaction;

final readonly class TransactionView
{
    public function __construct(
        public Transaction $transaction,
        public AccountNames $names,
    ) {}
}
