<?php

declare(strict_types=1);

namespace App\Shared\Errors;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Str;

final class ValidationCodes
{
    /**
     * @return array<string, list<string>>
     */
    public static function from(Validator $validator): array
    {
        $fields = [];

        foreach ($validator->failed() as $attribute => $rules) {
            $fieldCode = self::withoutIndexes((string) $attribute);
            $codes = array_map(
                fn (string $rule): string => $fieldCode.'.'.Str::snake(class_basename($rule)),
                array_keys($rules),
            );
            $fields[(string) $attribute] = array_values(array_unique($codes));
        }

        return $fields;
    }

    private static function withoutIndexes(string $attribute): string
    {
        $segments = array_filter(
            explode('.', $attribute),
            fn (string $segment): bool => ! ctype_digit($segment),
        );

        return implode('.', $segments);
    }
}
