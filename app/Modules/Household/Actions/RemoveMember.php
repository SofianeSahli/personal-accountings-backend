<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RemoveMember
{
    public function handle(Household $household, string $userId): void
    {
        $member = Str::isUuid($userId)
            ? HouseholdMember::query()->where('household_id', $household->id)->where('user_id', $userId)->first()
            : null;

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
        return new ApiException('member.not_found', Response::HTTP_NOT_FOUND);
    }
}
