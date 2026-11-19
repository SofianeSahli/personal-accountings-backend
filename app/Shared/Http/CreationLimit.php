<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class CreationLimit
{
    public const NAME = 'creations';

    private const PER_MINUTE = 10;

    public static function define(): void
    {
        RateLimiter::for(self::NAME, fn (Request $request): Limit => Limit::perMinute(self::PER_MINUTE)
            ->by('account:'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
