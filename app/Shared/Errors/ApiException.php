<?php

declare(strict_types=1);

namespace App\Shared\Errors;

use RuntimeException;
use Throwable;

final class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $fields
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $fields = [],
        public readonly ?int $retryAfterSeconds = null,
        public readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, 0, $previous);
    }
}
