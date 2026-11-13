<?php

declare(strict_types=1);

namespace App\Shared\Health;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Routing\Middleware\ThrottleRequests;

final class HealthThrottle extends ThrottleRequests
{
    private const STORE = 'file';

    public function __construct(CacheManager $cache)
    {
        parent::__construct(new RateLimiter($cache->store(self::STORE)));
    }
}
