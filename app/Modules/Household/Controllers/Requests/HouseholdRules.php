<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Requests;

final class HouseholdRules
{
    public const NAME_MAX_LENGTH = 60;

    /**
     * @return list<string>
     */
    public static function name(): array
    {
        return ['required', 'string', 'max:'.self::NAME_MAX_LENGTH];
    }
}
