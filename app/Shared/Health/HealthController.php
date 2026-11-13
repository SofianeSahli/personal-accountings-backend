<?php

declare(strict_types=1);

namespace App\Shared\Health;

final class HealthController
{
    private const STATUS_OK = 'ok';

    public function __invoke(CheckHealth $checkHealth): HealthResource
    {
        $checkHealth->handle();

        return new HealthResource(self::STATUS_OK);
    }
}
