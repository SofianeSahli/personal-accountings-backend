<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

enum SignInMode
{
    case Web;
    case Device;

    public static function of(Request $request): self
    {
        if (EnsureFrontendRequestsAreStateful::fromFrontend($request) && $request->hasSession()) {
            return self::Web;
        }

        return self::Device;
    }
}
