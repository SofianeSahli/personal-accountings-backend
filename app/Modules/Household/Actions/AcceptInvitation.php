<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class AcceptInvitation
{
    public function __construct(private readonly AddMember $addMember) {}

    public function handle(User $user, HouseholdInvitation $invitation): Household
    {
        $email = $user->verifiedEmail() ?? throw $this->notFound();

        try {
            return DB::transaction(function () use ($user, $invitation, $email): Household {
                $household = Household::lockLive($invitation->household_id) ?? throw $this->notFound();
                $locked = HouseholdInvitation::query()
                    ->pending()
                    ->whereKey($invitation->id)
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first() ?? throw $this->notFound();

                $this->addMember->handle($household, $user);
                $locked->update(['status' => InvitationStatus::Accepted, 'closed_at' => now()]);

                return $household;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ApiException('household.already_joined', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function notFound(): ApiException
    {
        return new ApiException('invitation.not_found', Response::HTTP_NOT_FOUND);
    }
}
