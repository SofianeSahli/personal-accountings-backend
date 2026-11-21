<?php

declare(strict_types=1);

namespace App\Modules\Household\Models;

enum HouseholdRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
