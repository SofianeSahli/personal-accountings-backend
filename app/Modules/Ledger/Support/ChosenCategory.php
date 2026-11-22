<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\Category;

final readonly class ChosenCategory
{
    public function __construct(
        public Category $category,
        public ?string $otherName,
    ) {}
}
