<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class LedgerWriteLimit
{
    public const NAME = 'ledger-writes';

    public const PER_MINUTE = 60;

    public static function define(): void
    {
        RateLimiter::for(self::NAME, fn (Request $request): Limit => Limit::perMinute(self::PER_MINUTE)
            ->by('ledger-writes:'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
