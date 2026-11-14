<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\DisableTwoFactor;
use App\Modules\Identity\Actions\EnableTwoFactor;
use App\Modules\Identity\Actions\SendTwoFactorDisableCode;
use App\Modules\Identity\Controllers\Requests\DisableTwoFactorRequest;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final class TwoFactorController
{
    public function store(#[CurrentUser] User $user, EnableTwoFactor $enableTwoFactor): Response
    {
        $enableTwoFactor->handle($user);

        return response()->noContent();
    }

    public function sendDisableCode(#[CurrentUser] User $user, SendTwoFactorDisableCode $sendTwoFactorDisableCode): Response
    {
        $sendTwoFactorDisableCode->handle($user);

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    public function destroy(DisableTwoFactorRequest $request, #[CurrentUser] User $user, DisableTwoFactor $disableTwoFactor): Response
    {
        $disableTwoFactor->handle($user, $request->validated('code'));

        return response()->noContent();
    }
}
