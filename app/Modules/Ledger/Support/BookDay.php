<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\Book;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class BookDay
{
    private const FORMAT = '!Y-m-d';

    private const TIMEZONE = 'UTC';

    public static function today(Book $book): string
    {
        return CarbonImmutable::now($book->timezone)->toDateString();
    }

    public static function addDays(string $date, int $days): string
    {
        return self::parse($date)->addDays($days)->toDateString();
    }

    public static function addYears(string $date, int $years): string
    {
        return self::parse($date)->addYearsNoOverflow($years)->toDateString();
    }

    public static function parse(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat(self::FORMAT, $date, self::TIMEZONE);

        if (! $parsed instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Invalid date.');
        }

        return $parsed;
    }
}
