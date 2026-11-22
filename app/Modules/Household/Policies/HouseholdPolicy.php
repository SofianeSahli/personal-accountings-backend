<?php

declare(strict_types=1);

namespace App\Modules\Household\Policies;

use App\Modules\Household\Actions\FindMembership;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Support\Membership;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

final class HouseholdPolicy
{
    public function __construct(private readonly FindMembership $findMembership) {}

    public function view(User $user, ?Household $household): Response
    {
        return $this->membershipIn($user, $household) === null ? $this->notFound() : Response::allow();
    }

    public function update(User $user, ?Household $household): Response
    {
        return $this->ownerOnly($user, $household);
    }

    public function delete(User $user, ?Household $household): Response
    {
        return $this->ownerOnly($user, $household);
    }

    public function manageMembers(User $user, ?Household $household): Response
    {
        return $this->ownerOnly($user, $household);
    }

    private function ownerOnly(User $user, ?Household $household): Response
    {
        $membership = $this->membershipIn($user, $household);

        return match (true) {
            $membership === null => $this->notFound(),
            $membership->isOwner => Response::allow(),
            default => Response::deny(code: 'household.forbidden'),
        };
    }

    private function membershipIn(User $user, ?Household $household): ?Membership
    {
        $membership = $this->findMembership->handle($user);

        return $household !== null && $membership?->householdId === $household->id ? $membership : null;
    }

    private function notFound(): Response
    {
        return Response::denyAsNotFound(code: 'household.not_found');
    }
}
