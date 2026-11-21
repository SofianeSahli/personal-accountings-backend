<?php

declare(strict_types=1);

namespace App\Modules\Household\Policies;

use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

final class InvitationPolicy
{
    public function answer(User $user, ?HouseholdInvitation $invitation): Response
    {
        $email = $user->verifiedEmail();

        $answerable = $invitation !== null
            && $email !== null
            && $invitation->email === $email
            && HouseholdInvitation::query()->pending()->whereKey($invitation->id)->exists();

        return $answerable ? Response::allow() : Response::denyAsNotFound(code: 'invitation.not_found');
    }
}
