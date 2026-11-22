<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class NewTransaction
{
    public function __construct(
        public ?string $id,
        public int $amount,
        public string $date,
        public string $categoryCode,
        public ?string $otherName,
        public ?string $comment,
    ) {}
}
