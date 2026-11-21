<?php

declare(strict_types=1);

namespace App\Modules\Household;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Policies\HouseholdPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class HouseholdServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Relation::morphMap([Household::MORPH_ALIAS => Household::class]);
        Gate::policy(Household::class, HouseholdPolicy::class);

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/routes.php');
    }
}
