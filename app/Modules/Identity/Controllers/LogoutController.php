<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Actions\SignOutDevice;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final class LogoutController
{
    public function __invoke(#[CurrentUser] User $user, SignOutDevice $signOutDevice): Response
    {
        $signOutDevice->handle($user);

        return response()->noContent();
    }
}
