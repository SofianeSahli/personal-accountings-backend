<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers;

use App\Modules\Household\Actions\LeaveHousehold;
use App\Modules\Household\Actions\ListMembers;
use App\Modules\Household\Actions\RemoveMember;
use App\Modules\Household\Controllers\Resources\MemberList;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Support\CurrentHousehold;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class HouseholdMemberController
{
    public function index(CurrentHousehold $current, ListMembers $listMembers): MemberList
    {
        return new MemberList($listMembers->handle($current->household));
    }

    public function destroy(CurrentHousehold $current, string $user, RemoveMember $removeMember): Response
    {
        Gate::authorize('manageMembers', [Household::class, $current->household]);

        $removeMember->handle($current->household, $user);

        return response()->noContent();
    }

    public function leave(CurrentHousehold $current, #[CurrentUser] User $caller, LeaveHousehold $leaveHousehold): Response
    {
        $leaveHousehold->handle($caller, $current->household);

        return response()->noContent();
    }
}
