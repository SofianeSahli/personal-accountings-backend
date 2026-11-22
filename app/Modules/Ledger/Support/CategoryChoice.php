<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\Category;

final readonly class CategoryChoice
{
    public function __construct(
        public ?string $code,
        public bool $sendsOtherName,
        public ?string $otherName,
        public ?Category $current = null,
        public ?string $currentOtherName = null,
    ) {}

    public static function forNew(string $code, ?string $otherName): self
    {
        return new self($code, $otherName !== null, $otherName);
    }
}
