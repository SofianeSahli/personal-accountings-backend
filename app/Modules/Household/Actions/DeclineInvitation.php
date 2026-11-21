<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class DeclineInvitation
{
    public function handle(User $user, HouseholdInvitation $invitation): void
    {
        $email = $user->verifiedEmail();

        $declined = $email !== null && HouseholdInvitation::query()
            ->pending()
            ->whereKey($invitation->id)
            ->where('email', $email)
            ->update(['status' => InvitationStatus::Declined->value, 'closed_at' => now()]) === 1;

        if (! $declined) {
            throw new ApiException('invitation.not_found', Response::HTTP_NOT_FOUND);
        }
    }
}
