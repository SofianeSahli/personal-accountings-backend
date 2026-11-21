<?php

declare(strict_types=1);

namespace App\Modules\Household\Tests\Support;

use App\Modules\Household\database\factories\HouseholdInvitationFactory;
use App\Modules\Household\database\factories\HouseholdQrInvitationFactory;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;
use App\Shared\Errors\ApiException;
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

    public static function invite(Household $household, string $email, ?HouseholdInvitationFactory $factory = null): HouseholdInvitation
    {
        return ($factory ?? HouseholdInvitation::factory())->create([
            'household_id' => $household->id,
            'email' => $email,
            'invited_by' => self::ownerIdOf($household),
        ]);
    }

    public static function qrInvitation(
        Household $household,
        string $token,
        ?HouseholdQrInvitationFactory $factory = null,
    ): HouseholdQrInvitation {
        return ($factory ?? HouseholdQrInvitation::factory())->withToken($token)->create([
            'household_id' => $household->id,
            'invited_by' => self::ownerIdOf($household),
        ]);
    }

    public static function refusalOf(callable $action): ?ApiException
    {
        try {
            $action();
        } catch (ApiException $refusal) {
            return $refusal;
        }

        return null;
    }

    public static function ownerIdOf(Household $household): string
    {
        return (string) HouseholdMember::query()
            ->where('household_id', $household->id)
            ->where('role', HouseholdRole::Owner)
            ->value('user_id');
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
