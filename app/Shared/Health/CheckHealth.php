<?php

declare(strict_types=1);

namespace App\Shared\Health;

use App\Shared\Errors\ApiException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

final class CheckHealth
{
    public function __construct(private readonly DatabaseManager $database) {}

    public function handle(): void
    {
        try {
            $this->database->connection()->select('select 1');
        } catch (QueryException|PDOException $exception) {
            throw new ApiException(
                'health.database_unavailable',
                Response::HTTP_SERVICE_UNAVAILABLE,
                previous: $exception,
            );
        }
    }
}
