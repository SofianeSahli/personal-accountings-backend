<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Household\Support\NewQrInvitation;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class CreateQrInvitation
{
    public function handle(User $inviter, Household $household): NewQrInvitation
    {
        return DB::transaction(function () use ($inviter, $household): NewQrInvitation {
            $locked = Household::lockLive($household->id)
                ?? throw new ApiException('household.not_found', Response::HTTP_NOT_FOUND);

            if ($locked->memberCount() >= Household::MEMBER_LIMIT) {
                throw new ApiException('household.member_limit', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            HouseholdQrInvitation::query()
                ->where('household_id', $locked->id)
                ->where('status', InvitationStatus::Pending->value)
                ->update(['status' => InvitationStatus::Cancelled->value, 'closed_at' => now()]);

            $token = HouseholdQrInvitation::newToken();
            $invitation = HouseholdQrInvitation::query()->create([
                'household_id' => $locked->id,
                'invited_by' => $inviter->id,
                'token_hash' => HouseholdQrInvitation::hashOf($token),
                'status' => InvitationStatus::Pending,
                'expires_at' => now()->addMinutes(HouseholdQrInvitation::VALID_MINUTES),
            ]);

            return new NewQrInvitation($invitation, $token);
        });
    }
}
