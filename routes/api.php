<?php

use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\MoneyTransitionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('redis.auth')->group(function () {
    Route::post('/profile', [ProfileController::class, 'create']);
    Route::patch('/profile/{user}', [ProfileController::class, 'update']);
    Route::get('/categories', [CategoriesController::class, 'get']);
});

Route::middleware(['redis.auth', 'query.user'])->group(function () {
    Route::post('/money-transition', [MoneyTransitionController::class, 'create']);
    Route::patch('/money-transition/{moneyTransition}', [MoneyTransitionController::class, 'update']);
});
