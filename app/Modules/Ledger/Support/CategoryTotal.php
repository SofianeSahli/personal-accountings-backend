<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\CategoryType;

final readonly class CategoryTotal
{
    /**
     * @param  list<CategoryTotal>  $children
     * @param  list<OtherNameTotal>  $otherNames
     */
    public function __construct(
        public string $code,
        public CategoryType $type,
        public int $total,
        public int $entries,
        public array $children,
        public array $otherNames,
    ) {}
}
