<?php

declare(strict_types=1);

namespace App\Modules\Household;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Household\Policies\HouseholdPolicy;
use App\Modules\Household\Policies\InvitationPolicy;
use App\Modules\Household\Policies\QrInvitationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class HouseholdServiceProvider extends ServiceProvider
{
    public const QR_SCAN_LIMIT = 'household-qr';

    private const QR_SCANS_PER_MINUTE = 10;

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'household');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'household');

        Relation::morphMap([Household::MORPH_ALIAS => Household::class]);
        Gate::policy(Household::class, HouseholdPolicy::class);
        Gate::policy(HouseholdInvitation::class, InvitationPolicy::class);
        Gate::policy(HouseholdQrInvitation::class, QrInvitationPolicy::class);

        RateLimiter::for(self::QR_SCAN_LIMIT, fn (Request $request): Limit => Limit::perMinute(self::QR_SCANS_PER_MINUTE)
            ->by('account:'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/routes.php');
    }
}
