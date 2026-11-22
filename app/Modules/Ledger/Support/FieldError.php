<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class FieldError
{
    public static function of(string $field, string $code): ApiException
    {
        return new ApiException(
            'validation.failed',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            fields: [$field => [$code]],
        );
    }
}
