<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\SignInWithPassword;
use App\Modules\Identity\Controllers\Requests\IdentityRules;
use App\Modules\Identity\Controllers\Requests\PasswordSignInRequest;
use App\Modules\Identity\Controllers\Resources\SessionResource;

final class PasswordSignInController
{
    public function __invoke(PasswordSignInRequest $request, SignInWithPassword $signInWithPassword): SessionResource
    {
        return new SessionResource($signInWithPassword->handle(
            $request->validated('email'),
            $request->validated('password'),
            IdentityRules::signInTarget($request),
        ));
    }
}
