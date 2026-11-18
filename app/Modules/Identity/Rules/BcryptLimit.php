<?php

declare(strict_types=1);

namespace App\Modules\Identity\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class BcryptLimit implements ValidationRule
{
    private const MAX_BYTES = 72;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > self::MAX_BYTES) {
            $fail('password.bcrypt_limit');
        }
    }
}
