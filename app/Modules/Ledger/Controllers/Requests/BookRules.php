<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Requests;

use App\Modules\Ledger\Rules\IanaTimezone;
use Illuminate\Contracts\Validation\ValidationRule;

final class BookRules
{
    public const NAME_MAX_LENGTH = 60;

    public const DEFAULT_CURRENCY = 'TND';

    public const DEFAULT_TIMEZONE = 'Africa/Tunis';

    /**
     * @return list<string>
     */
    public static function name(): array
    {
        return ['required', 'string', 'max:'.self::NAME_MAX_LENGTH];
    }

    /**
     * @return list<string|ValidationRule>
     */
    public static function timezone(): array
    {
        return ['bail', 'string', new IanaTimezone];
    }
}
