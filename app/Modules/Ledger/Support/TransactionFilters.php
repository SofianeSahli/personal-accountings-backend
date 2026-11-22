<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\CategoryType;

final readonly class TransactionFilters
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public ?string $categoryCode = null,
        public ?CategoryType $type = null,
        public ?string $authorId = null,
    ) {}
}
