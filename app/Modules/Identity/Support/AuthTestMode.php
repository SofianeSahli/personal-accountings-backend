<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

final class AuthTestMode
{
    public static function isOn(): bool
    {
        return config('identity.auth_test_mode') === true && ! app()->isProduction();
    }
}
