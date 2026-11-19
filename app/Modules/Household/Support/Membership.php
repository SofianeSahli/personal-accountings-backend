<?php

declare(strict_types=1);

namespace App\Modules\Household\Support;

final readonly class Membership
{
    public function __construct(
        public string $householdId,
        public bool $isOwner,
    ) {}
}
