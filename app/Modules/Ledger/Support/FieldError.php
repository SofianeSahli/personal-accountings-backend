<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class FieldError
{
    public static function of(string $field, string $code): ApiException
    {
        return self::each([$field => $code]);
    }

    /**
     * @param  array<string, string>  $codes
     */
    public static function each(array $codes): ApiException
    {
        return new ApiException(
            'validation.failed',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            fields: array_map(fn (string $code): array => [$code], $codes),
        );
    }
}
