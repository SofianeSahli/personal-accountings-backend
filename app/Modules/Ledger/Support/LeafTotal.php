<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\CategoryType;

final readonly class LeafTotal
{
    public function __construct(
        public string $categoryId,
        public CategoryType $type,
        public int $total,
        public int $entries,
    ) {}
}
