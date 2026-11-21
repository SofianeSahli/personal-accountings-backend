<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Http\CreationLimit;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        CreationLimit::define();
    }
}
