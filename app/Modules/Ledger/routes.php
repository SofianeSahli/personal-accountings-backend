<?php

declare(strict_types=1);

use App\Modules\Ledger\Controllers\BookController;
use App\Modules\Ledger\Controllers\Middleware\RequireBook;
use App\Shared\Http\CreationLimit;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('books', [BookController::class, 'index']);
    Route::post('books', [BookController::class, 'store'])
        ->middleware('throttle:'.CreationLimit::NAME);

    Route::middleware(RequireBook::class)->group(function (): void {
        Route::get('books/{book}', [BookController::class, 'show']);
        Route::patch('books/{book}', [BookController::class, 'update']);
    });
});
