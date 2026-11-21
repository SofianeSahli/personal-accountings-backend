<?php

declare(strict_types=1);

namespace App\Modules\Household\Policies;

use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

final class QrInvitationPolicy
{
    public function use(User $user, ?HouseholdQrInvitation $invitation): Response
    {
        $usable = $invitation !== null
            && $user->verifiedEmail() !== null
            && HouseholdQrInvitation::query()->pending()->whereKey($invitation->id)->exists();

        return $usable ? Response::allow() : Response::denyAsNotFound(code: 'invitation.not_found');
    }
}
