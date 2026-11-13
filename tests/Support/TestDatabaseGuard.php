<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class TestDatabaseGuard
{
    private const REQUIRED_SUFFIX = '_test';

    public static function ensure(string $database): void
    {
        if (! str_ends_with($database, self::REQUIRED_SUFFIX)) {
            throw new RuntimeException(
                "Refusing to run tests on database [{$database}]: its name must end in ".self::REQUIRED_SUFFIX.'.'
            );
        }
    }
}
