<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\RecurringRule;

final readonly class RecurringRuleView
{
    public function __construct(
        public RecurringRule $rule,
        public AccountNames $names,
    ) {}
}
