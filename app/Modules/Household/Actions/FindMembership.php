<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Support\Membership;
use App\Modules\Identity\Models\User;

final class FindMembership
{
    public function handle(User $user): ?Membership
    {
        $member = HouseholdMember::query()
            ->where('user_id', $user->id)
            ->whereHas('household')
            ->first();

        return $member === null ? null : new Membership($member->household_id, $member->isOwner());
    }
}
