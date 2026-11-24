<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class OtherNameTotal
{
    public function __construct(
        public string $name,
        public int $total,
        public int $entries,
    ) {}
}
