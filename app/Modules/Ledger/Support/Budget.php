<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class Budget
{
    /**
     * @param  list<BudgetLine>  $lines
     */
    public function __construct(
        public BudgetMonth $month,
        public string $currency,
        public bool $hasPlan,
        public ?int $daysLeft,
        public ?int $expectedIncome,
        public int $actualIncome,
        public ?int $plannedSaving,
        public int $actualSaving,
        public ?int $plannedExpenses,
        public int $actualExpenses,
        public ?int $offPlan,
        public ?int $remaining,
        public array $lines,
    ) {}
}
