<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class NewBudgetPlan
{
    /**
     * @param  list<PlanLineInput>  $lines
     */
    public function __construct(
        public BudgetMonth $month,
        public ?int $expectedIncome,
        public ?int $plannedSaving,
        public array $lines,
    ) {}
}
