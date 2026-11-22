<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers;

use App\Modules\Household\Actions\CreateQrInvitation;
use App\Modules\Household\Controllers\Resources\NewQrInvitationResource;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Support\CurrentHousehold;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class HouseholdQrInvitationController
{
    public function store(CurrentHousehold $current, #[CurrentUser] User $user, CreateQrInvitation $createQrInvitation): JsonResponse
    {
        Gate::authorize('manageMembers', [Household::class, $current->household]);

        return (new NewQrInvitationResource($createQrInvitation->handle($user, $current->household)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
