<?php

declare(strict_types=1);

namespace App\Modules\Household\Tests\Support;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;
use Illuminate\Support\Facades\Auth;

final class HouseholdScenario
{
    public static function ownedBy(User $owner, string $name = 'Foyer Sahli'): Household
    {
        return Household::factory()->ownedBy($owner)->create(['name' => $name]);
    }

    public static function join(Household|string $household, User $user, HouseholdRole $role = HouseholdRole::Member): HouseholdMember
    {
        return HouseholdMember::factory()->create([
            'household_id' => $household instanceof Household ? $household->id : $household,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    public static function leave(User $user): void
    {
        HouseholdMember::query()->where('user_id', $user->id)->delete();
    }

    public static function memberOf(Household $household): User
    {
        $member = User::factory()->create();
        self::join($household, $member);

        return $member;
    }

    /**
     * @return array<string, string>
     */
    public static function as(User $user): array
    {
        Auth::forgetGuards();

        return IdentityScenario::bearer(IdentityScenario::tokenFor($user));
    }
}
