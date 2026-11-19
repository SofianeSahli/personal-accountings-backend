<?php

declare(strict_types=1);

namespace App\Modules\Household\Support;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdRole;

final readonly class CurrentHousehold
{
    public function __construct(
        public Household $household,
        public HouseholdRole $role,
    ) {}
}
