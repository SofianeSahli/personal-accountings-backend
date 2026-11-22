<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Support\RecurringRuleView;

final class ShowRecurringRule
{
    public function __construct(private readonly NameAccounts $nameAccounts) {}

    public function handle(RecurringRule $rule): RecurringRuleView
    {
        $rule->loadMissing('category');

        return new RecurringRuleView($rule, $this->nameAccounts->handle([$rule]));
    }
}
