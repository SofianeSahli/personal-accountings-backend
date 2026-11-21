<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class AddMember
{
    public function __construct(private readonly FindMembership $findMembership) {}

    public function handle(Household $household, User $user): void
    {
        if ($this->findMembership->handle($user) !== null) {
            throw new ApiException('household.already_joined', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($household->memberCount() >= Household::MEMBER_LIMIT) {
            throw new ApiException('household.member_limit', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        HouseholdMember::query()->create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => HouseholdRole::Member,
        ]);
    }
}
