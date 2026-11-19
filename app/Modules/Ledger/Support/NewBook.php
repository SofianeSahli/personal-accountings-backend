<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Shared\Money\Currency;

final readonly class NewBook
{
    public function __construct(
        public string $name,
        public Currency $currency,
        public string $timezone,
        public ?string $householdId,
    ) {}
}
