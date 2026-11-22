<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Models\Frequency;
use App\Modules\Ledger\Support\BookDay;
use App\Modules\Ledger\Support\CurrentBook;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class MoneyRules
{
    public const MAX_AMOUNT = 999_999_999_999;

    public const OTHER_NAME_MAX_LENGTH = 60;

    public const COMMENT_MAX_LENGTH = 200;

    public const EARLIEST_DATE = '2000-01-01';

    public const YEARS_AHEAD = 1;

    public const DATE_FORMAT = 'date_format:Y-m-d';

    public const MIN_INTERVAL = 1;

    public const MAX_INTERVAL = 12;

    /**
     * @return list<string>
     */
    public static function amount(): array
    {
        return ['integer:strict', 'min:1', 'max:'.self::MAX_AMOUNT];
    }

    /**
     * @return list<string>
     */
    public static function entryDate(): array
    {
        return [
            'bail',
            self::DATE_FORMAT,
            'after_or_equal:'.self::EARLIEST_DATE,
            'before_or_equal:'.self::latestDate(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function startDate(): array
    {
        $today = BookDay::today(self::book()->book);

        return [
            'bail',
            self::DATE_FORMAT,
            'after_or_equal:'.BookDay::addYears($today, -self::YEARS_AHEAD),
            'before_or_equal:'.self::latestDate(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function interval(): array
    {
        return ['integer:strict', 'min:'.self::MIN_INTERVAL, 'max:'.self::MAX_INTERVAL];
    }

    public static function frequency(): In
    {
        return Rule::in(array_column(Frequency::cases(), 'value'));
    }

    /**
     * @return list<string>
     */
    public static function otherName(): array
    {
        return ['nullable', 'string', 'max:'.self::OTHER_NAME_MAX_LENGTH];
    }

    /**
     * @return list<string>
     */
    public static function comment(): array
    {
        return ['nullable', 'string', 'max:'.self::COMMENT_MAX_LENGTH];
    }

    public static function optionalString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private static function latestDate(): string
    {
        return BookDay::addYears(BookDay::today(self::book()->book), self::YEARS_AHEAD);
    }

    private static function book(): CurrentBook
    {
        return app(CurrentBook::class);
    }
}
