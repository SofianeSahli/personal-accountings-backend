<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class LeaveHousehold
{
    public function handle(User $user, Household $household): void
    {
        $member = HouseholdMember::query()
            ->where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->first();

        if ($member === null) {
            throw $this->notFound();
        }

        if ($member->isOwner()) {
            throw new ApiException('household.owner_cannot_leave', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $member->endMembership()) {
            throw $this->notFound();
        }
    }

    private function notFound(): ApiException
    {
        return new ApiException('household.not_found', Response::HTTP_NOT_FOUND);
    }
}
