<?php

use App\Http\Middleware\QueryUserMiddleware;
use App\Http\Middleware\RedisAuthMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'redis.auth' => RedisAuthMiddleware::class,
            'query.user' => QueryUserMiddleware::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, $request) {
            return response()->json([
                'errors' => collect($e->errors())->flatten()
                    ->unique()
                    ->values()
                    ->all(),
            ], 422);
        });
    })->create()
;
