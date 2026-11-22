<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\Frequency;

final readonly class NewRecurringRule
{
    public function __construct(
        public int $amount,
        public string $categoryCode,
        public ?string $otherName,
        public ?string $comment,
        public Frequency $frequency,
        public int $interval,
        public string $startsOn,
        public ?string $endsOn,
    ) {}
}
