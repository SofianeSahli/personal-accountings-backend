<?php

declare(strict_types=1);

use App\Modules\Ledger\Controllers\BookController;
use App\Modules\Ledger\Controllers\BudgetController;
use App\Modules\Ledger\Controllers\CategoryController;
use App\Modules\Ledger\Controllers\Middleware\RequireBook;
use App\Modules\Ledger\Controllers\Middleware\RequireRecurringRule;
use App\Modules\Ledger\Controllers\Middleware\RequireTransaction;
use App\Modules\Ledger\Controllers\OtherNameController;
use App\Modules\Ledger\Controllers\RecurringRuleController;
use App\Modules\Ledger\Controllers\SummaryController;
use App\Modules\Ledger\Controllers\TransactionController;
use App\Modules\Ledger\Support\LedgerWriteLimit;
use App\Shared\Http\CreationLimit;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Support\Facades\Route;

$writes = 'throttle:'.LedgerWriteLimit::NAME;

Route::middleware('auth:sanctum')->group(function () use ($writes): void {
    Route::get('categories', [CategoryController::class, 'index'])
        ->middleware(['throttle:60,1', SetCacheHeaders::using('private;no_cache;etag')]);

    Route::get('books', [BookController::class, 'index']);
    Route::post('books', [BookController::class, 'store'])
        ->middleware('throttle:'.CreationLimit::NAME);

    Route::middleware(RequireBook::class)->prefix('books/{book}')->group(function () use ($writes): void {
        Route::get('', [BookController::class, 'show']);
        Route::patch('', [BookController::class, 'update']);

        Route::get('transactions', [TransactionController::class, 'index']);
        Route::post('transactions', [TransactionController::class, 'store'])->middleware($writes);

        Route::middleware(RequireTransaction::class)->group(function () use ($writes): void {
            Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
            Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])->middleware($writes);
            Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->middleware($writes);
        });

        Route::get('recurring-rules', [RecurringRuleController::class, 'index']);
        Route::post('recurring-rules', [RecurringRuleController::class, 'store'])->middleware($writes);

        Route::middleware(RequireRecurringRule::class)->group(function () use ($writes): void {
            Route::get('recurring-rules/{rule}', [RecurringRuleController::class, 'show']);
            Route::patch('recurring-rules/{rule}', [RecurringRuleController::class, 'update'])->middleware($writes);
            Route::delete('recurring-rules/{rule}', [RecurringRuleController::class, 'destroy'])->middleware($writes);
        });

        Route::get('other-names', [OtherNameController::class, 'index']);

        Route::get('summary', [SummaryController::class, 'show']);

        Route::get('budgets/{month}', [BudgetController::class, 'show']);
        Route::put('budgets/{month}', [BudgetController::class, 'update'])->middleware($writes);
        Route::post('budgets/{month}/copy', [BudgetController::class, 'copy'])->middleware($writes);
    });
});
