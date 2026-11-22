<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class DueEntriesReport
{
    public function __construct(
        public int $rules,
        public int $entries,
        public int $failures,
    ) {}
}
