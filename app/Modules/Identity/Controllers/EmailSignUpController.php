<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\ConfirmEmail;
use App\Modules\Identity\Actions\RegisterWithEmail;
use App\Modules\Identity\Actions\ResendEmailCode;
use App\Modules\Identity\Controllers\Requests\ConfirmEmailRequest;
use App\Modules\Identity\Controllers\Requests\EmailAddressRequest;
use App\Modules\Identity\Controllers\Requests\IdentityRules;
use App\Modules\Identity\Controllers\Requests\RegisterRequest;
use App\Modules\Identity\Controllers\Resources\SessionResource;
use App\Modules\Identity\Support\NewAccount;
use Illuminate\Http\Response;

final class EmailSignUpController
{
    public function register(RegisterRequest $request, RegisterWithEmail $registerWithEmail): Response
    {
        $registerWithEmail->handle(new NewAccount(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            termsVersion: $request->validated('terms_version'),
            phone: IdentityRules::normalizedPhone($request),
            locale: IdentityRules::chosenLocale($request),
        ));

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    public function confirm(ConfirmEmailRequest $request, ConfirmEmail $confirmEmail): SessionResource
    {
        return new SessionResource($confirmEmail->handle(
            $request->validated('email'),
            $request->validated('code'),
            IdentityRules::signInTarget($request),
        ));
    }

    public function resend(EmailAddressRequest $request, ResendEmailCode $resendEmailCode): Response
    {
        $resendEmailCode->handle($request->validated('email'));

        return response()->noContent(Response::HTTP_ACCEPTED);
    }
}
