<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class SyncReport
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $retired,
        public int $restored,
        public int $unchanged,
    ) {}
}
