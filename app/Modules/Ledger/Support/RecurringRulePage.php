<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\RecurringRule;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class RecurringRulePage
{
    /**
     * @param  CursorPaginator<int, RecurringRule>  $page
     */
    public function __construct(
        public CursorPaginator $page,
        public AccountNames $names,
    ) {}
}
