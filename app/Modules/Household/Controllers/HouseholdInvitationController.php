<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers;

use App\Modules\Household\Actions\CancelInvitation;
use App\Modules\Household\Actions\InviteMember;
use App\Modules\Household\Actions\ListHouseholdInvitations;
use App\Modules\Household\Controllers\Requests\InviteMemberRequest;
use App\Modules\Household\Controllers\Resources\InvitationList;
use App\Modules\Household\Controllers\Resources\InvitationResource;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Support\CurrentHousehold;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class HouseholdInvitationController
{
    public function index(CurrentHousehold $current, ListHouseholdInvitations $listInvitations): InvitationList
    {
        Gate::authorize('manageMembers', [Household::class, $current->household]);

        return new InvitationList($listInvitations->handle($current->household));
    }

    public function store(
        InviteMemberRequest $request,
        CurrentHousehold $current,
        #[CurrentUser] User $user,
        InviteMember $inviteMember,
    ): JsonResponse {
        $invitation = $inviteMember->handle($user, $current->household, $request->invitedEmail());

        return (new InvitationResource($invitation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(CurrentHousehold $current, string $invitation, CancelInvitation $cancelInvitation): Response
    {
        Gate::authorize('manageMembers', [Household::class, $current->household]);

        $cancelInvitation->handle($current->household, $invitation);

        return response()->noContent();
    }
}
