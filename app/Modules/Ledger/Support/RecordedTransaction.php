<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class RecordedTransaction
{
    public function __construct(
        public TransactionView $view,
        public bool $created,
    ) {}
}
