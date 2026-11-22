<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers;

use App\Modules\Household\Actions\AcceptInvitation;
use App\Modules\Household\Actions\DeclineInvitation;
use App\Modules\Household\Actions\ListMyInvitations;
use App\Modules\Household\Controllers\Resources\HouseholdResource;
use App\Modules\Household\Controllers\Resources\ReceivedInvitationList;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class InvitationController
{
    public function index(#[CurrentUser] User $user, ListMyInvitations $listMyInvitations): ReceivedInvitationList
    {
        return new ReceivedInvitationList($listMyInvitations->handle($user));
    }

    public function accept(#[CurrentUser] User $user, string $invitation, AcceptInvitation $acceptInvitation): HouseholdResource
    {
        $household = $acceptInvitation->handle($user, $this->answerable($invitation));

        return new HouseholdResource($household, HouseholdRole::Member);
    }

    public function decline(#[CurrentUser] User $user, string $invitation, DeclineInvitation $declineInvitation): Response
    {
        $declineInvitation->handle($user, $this->answerable($invitation));

        return response()->noContent();
    }

    private function answerable(string $id): HouseholdInvitation
    {
        $invitation = HouseholdInvitation::findByRawId($id);
        Gate::authorize('answer', [HouseholdInvitation::class, $invitation]);

        return $invitation ?? throw new ApiException('invitation.not_found', Response::HTTP_NOT_FOUND);
    }
}
