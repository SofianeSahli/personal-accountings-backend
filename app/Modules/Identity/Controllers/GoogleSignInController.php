<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\SignInWithGoogle;
use App\Modules\Identity\Controllers\Requests\GoogleSignInRequest;
use App\Modules\Identity\Controllers\Requests\IdentityRules;
use App\Modules\Identity\Controllers\Resources\SessionResource;
use App\Modules\Identity\Support\GoogleSignUp;

final class GoogleSignInController
{
    public function __invoke(GoogleSignInRequest $request, SignInWithGoogle $signInWithGoogle): SessionResource
    {
        return new SessionResource($signInWithGoogle->handle(
            $request->validated('id_token'),
            IdentityRules::signInTarget($request),
            new GoogleSignUp(
                termsVersion: $request->validated('terms_version'),
                phone: IdentityRules::normalizedPhone($request),
                locale: IdentityRules::chosenLocale($request),
            ),
        ));
    }
}
