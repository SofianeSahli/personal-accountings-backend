<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;

final class StartWebSession
{
    private const WEB_GUARD = 'web';

    public function handle(User $user): void
    {
        Auth::guard(self::WEB_GUARD)->login($user);
    }
}
