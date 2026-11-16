<?php

declare(strict_types=1);

use App\Shared\Errors\ApiErrorRenderer;
use App\Shared\Errors\ApiException;
use App\Shared\Http\AssignRequestId;
use App\Shared\Http\RequireJson;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->statefulApi();
        $middleware->api(append: [RequireJson::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*'));
        $exceptions->dontReport(ApiException::class);
        $exceptions->report(function (QueryException $exception): false {
            Log::error('database.query_failed', ['sql_state' => $exception->getCode()]);

            return false;
        });
        $exceptions->render(new ApiErrorRenderer);
    })
    ->create();
