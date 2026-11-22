<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\Frequency;

final readonly class RecurringRuleChanges
{
    public function __construct(
        public ?int $amount = null,
        public ?string $categoryCode = null,
        public bool $sendsOtherName = false,
        public ?string $otherName = null,
        public bool $sendsComment = false,
        public ?string $comment = null,
        public ?Frequency $frequency = null,
        public ?int $interval = null,
        public ?string $startsOn = null,
        public bool $sendsEndsOn = false,
        public ?string $endsOn = null,
    ) {}
}
