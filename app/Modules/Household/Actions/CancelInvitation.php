<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class CancelInvitation
{
    public function handle(Household $household, string $invitationId): void
    {
        $cancelled = Str::isUuid($invitationId) && HouseholdInvitation::query()
            ->pending()
            ->whereKey($invitationId)
            ->where('household_id', $household->id)
            ->update(['status' => InvitationStatus::Cancelled->value, 'closed_at' => now()]) === 1;

        if (! $cancelled) {
            throw new ApiException('invitation.not_found', Response::HTTP_NOT_FOUND);
        }
    }
}
