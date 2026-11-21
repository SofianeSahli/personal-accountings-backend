<?php

declare(strict_types=1);

use App\Modules\Household\Controllers\HouseholdController;
use App\Modules\Household\Controllers\HouseholdInvitationController;
use App\Modules\Household\Controllers\HouseholdMemberController;
use App\Modules\Household\Controllers\HouseholdQrInvitationController;
use App\Modules\Household\Controllers\InvitationController;
use App\Modules\Household\Controllers\Middleware\RequireHousehold;
use App\Modules\Household\Controllers\QrInvitationController;
use App\Modules\Household\HouseholdServiceProvider;
use App\Shared\Http\CreationLimit;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('households', [HouseholdController::class, 'index']);
    Route::post('households', [HouseholdController::class, 'store'])
        ->middleware('throttle:'.CreationLimit::NAME);

    Route::get('invitations', [InvitationController::class, 'index']);
    Route::post('invitations/{invitation}/accept', [InvitationController::class, 'accept']);
    Route::post('invitations/{invitation}/decline', [InvitationController::class, 'decline']);

    Route::middleware('throttle:'.HouseholdServiceProvider::QR_SCAN_LIMIT)->group(function (): void {
        Route::post('qr-invitations/preview', [QrInvitationController::class, 'preview']);
        Route::post('qr-invitations/accept', [QrInvitationController::class, 'accept']);
    });

    Route::post('households/{household}/qr-invitations', [HouseholdQrInvitationController::class, 'store'])
        ->middleware(['throttle:'.CreationLimit::NAME, RequireHousehold::class]);

    Route::middleware(RequireHousehold::class)->group(function (): void {
        Route::get('households/{household}', [HouseholdController::class, 'show']);
        Route::patch('households/{household}', [HouseholdController::class, 'update']);
        Route::delete('households/{household}', [HouseholdController::class, 'destroy']);
        Route::get('households/{household}/members', [HouseholdMemberController::class, 'index']);
        Route::delete('households/{household}/members/{user}', [HouseholdMemberController::class, 'destroy']);
        Route::post('households/{household}/leave', [HouseholdMemberController::class, 'leave']);
        Route::get('households/{household}/invitations', [HouseholdInvitationController::class, 'index']);
        Route::post('households/{household}/invitations', [HouseholdInvitationController::class, 'store']);
        Route::delete('households/{household}/invitations/{invitation}', [HouseholdInvitationController::class, 'destroy']);
    });
});
