<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

enum EmailCodePurpose: string
{
    case EmailConfirmation = 'email_confirmation';
    case PasswordReset = 'password_reset';
    case TwoFactorDisable = 'two_factor_disable';
    case SignIn = 'sign_in';
}
