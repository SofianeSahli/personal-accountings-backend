<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class MemberTotal
{
    public function __construct(
        public string $accountId,
        public ?string $name,
        public TypeTotals $totals,
    ) {}
}
