<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class Summary
{
    /**
     * @param  list<CategoryTotal>  $categories
     * @param  list<MemberTotal>  $members
     * @param  array<string, TypeTotals>  $days
     * @param  array<string, TypeTotals>  $months
     */
    public function __construct(
        public SummaryPeriod $period,
        public string $currency,
        public TypeTotals $totals,
        public array $categories,
        public array $members,
        public array $days,
        public array $months,
    ) {}
}
