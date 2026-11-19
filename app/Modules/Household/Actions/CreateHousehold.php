<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class CreateHousehold
{
    public function __construct(private readonly FindMembership $findMembership) {}

    public function handle(User $owner, string $name): Household
    {
        if ($this->findMembership->handle($owner) !== null) {
            throw $this->limitReached();
        }

        try {
            return DB::transaction(function () use ($owner, $name): Household {
                $household = Household::query()->create(['name' => $name]);
                HouseholdMember::query()->create([
                    'household_id' => $household->id,
                    'user_id' => $owner->id,
                    'role' => HouseholdRole::Owner,
                ]);

                return $household;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->limitReached();
        }
    }

    private function limitReached(): ApiException
    {
        return new ApiException('household.limit_reached', Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
