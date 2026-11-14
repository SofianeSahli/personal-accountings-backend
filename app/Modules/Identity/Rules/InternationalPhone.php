<?php

declare(strict_types=1);

namespace App\Modules\Identity\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class InternationalPhone implements ValidationRule
{
    private const E164_PATTERN = '/^\+[1-9]\d{6,14}$/D';

    private const SEPARATORS_PATTERN = '/[\s\-()]/';

    private const INTERNATIONAL_PREFIX = '00';

    public static function normalize(string $phone): string
    {
        $compact = (string) preg_replace(self::SEPARATORS_PATTERN, '', $phone);

        return str_starts_with($compact, self::INTERNATIONAL_PREFIX)
            ? '+'.substr($compact, strlen(self::INTERNATIONAL_PREFIX))
            : $compact;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::E164_PATTERN, self::normalize($value)) !== 1) {
            $fail('phone.international_phone');
        }
    }
}
