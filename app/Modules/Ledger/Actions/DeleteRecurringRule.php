<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\RecurringRule;

final class DeleteRecurringRule
{
    public function handle(RecurringRule $rule): void
    {
        $rule->delete();
    }
}
