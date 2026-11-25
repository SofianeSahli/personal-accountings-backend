<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class PlanLineInput
{
    public function __construct(
        public string $category,
        public int $planned,
    ) {}
}
