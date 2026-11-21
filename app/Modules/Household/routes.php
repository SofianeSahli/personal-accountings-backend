<?php

declare(strict_types=1);

use App\Modules\Household\Controllers\HouseholdController;
use App\Modules\Household\Controllers\HouseholdMemberController;
use App\Modules\Household\Controllers\Middleware\RequireHousehold;
use App\Shared\Http\CreationLimit;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('households', [HouseholdController::class, 'index']);
    Route::post('households', [HouseholdController::class, 'store'])
        ->middleware('throttle:'.CreationLimit::NAME);

    Route::middleware(RequireHousehold::class)->group(function (): void {
        Route::get('households/{household}', [HouseholdController::class, 'show']);
        Route::patch('households/{household}', [HouseholdController::class, 'update']);
        Route::delete('households/{household}', [HouseholdController::class, 'destroy']);
        Route::get('households/{household}/members', [HouseholdMemberController::class, 'index']);
    });
});
