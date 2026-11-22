<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers;

use App\Modules\Household\Actions\AcceptQrInvitation;
use App\Modules\Household\Actions\PreviewQrInvitation;
use App\Modules\Household\Controllers\Requests\QrTokenRequest;
use App\Modules\Household\Controllers\Resources\HouseholdResource;
use App\Modules\Household\Controllers\Resources\QrInvitationPreviewResource;
use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class QrInvitationController
{
    public function preview(QrTokenRequest $request, PreviewQrInvitation $previewQrInvitation): QrInvitationPreviewResource
    {
        return new QrInvitationPreviewResource($previewQrInvitation->handle($this->usable($request->token())));
    }

    public function accept(QrTokenRequest $request, #[CurrentUser] User $user, AcceptQrInvitation $acceptQrInvitation): HouseholdResource
    {
        $household = $acceptQrInvitation->handle($user, $this->usable($request->token()));

        return new HouseholdResource($household, HouseholdRole::Member);
    }

    private function usable(string $token): HouseholdQrInvitation
    {
        $invitation = HouseholdQrInvitation::findByToken($token);
        Gate::authorize('use', [HouseholdQrInvitation::class, $invitation]);

        return $invitation ?? throw new ApiException('invitation.not_found', Response::HTTP_NOT_FOUND);
    }
}
