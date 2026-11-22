<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class TransactionChanges
{
    public function __construct(
        public ?int $amount = null,
        public ?string $date = null,
        public ?string $categoryCode = null,
        public bool $sendsOtherName = false,
        public ?string $otherName = null,
        public bool $sendsComment = false,
        public ?string $comment = null,
    ) {}
}
