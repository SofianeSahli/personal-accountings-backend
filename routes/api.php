<?php

declare(strict_types=1);

use App\Shared\Health\HealthController;
use App\Shared\Health\HealthThrottle;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)
    ->middleware(HealthThrottle::with(maxAttempts: 60, decayMinutes: 1));
