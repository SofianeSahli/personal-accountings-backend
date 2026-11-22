<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\CategoryLevel;
use App\Modules\Ledger\Models\CategoryType;

final readonly class CatalogueEntry
{
    public function __construct(
        public string $code,
        public ?string $parentCode,
        public CategoryLevel $level,
        public CategoryType $type,
        public ?string $emoji,
        public int $position,
        public bool $isOther,
    ) {}
}
