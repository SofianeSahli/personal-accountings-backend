<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PersonalAccessToken;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;

final class SignOutDevice
{
    private const WEB_GUARD = 'web';

    public function handle(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();

            return;
        }

        Auth::guard(self::WEB_GUARD)->logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
