<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

enum BudgetStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Over = 'over';

    private const WARNING_PERCENT = 85;

    private const HUNDRED = 100;

    public static function of(int $planned, int $actual): self
    {
        return match (true) {
            $actual > $planned => self::Over,
            $planned > 0 && $actual * self::HUNDRED >= $planned * self::WARNING_PERCENT => self::Warning,
            default => self::Ok,
        };
    }

    public static function percentOf(int $planned, int $actual): ?int
    {
        return $planned === 0 ? null : intdiv($actual * self::HUNDRED, $planned);
    }
}
