<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\Transaction;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class TransactionPage
{
    /**
     * @param  CursorPaginator<int, Transaction>  $page
     */
    public function __construct(
        public CursorPaginator $page,
        public AccountNames $names,
    ) {}
}
