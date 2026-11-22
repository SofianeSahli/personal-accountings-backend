<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Middleware;

use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Support\CurrentBook;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class RequireRecurringRule
{
    private const PARAMETER = 'rule';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $book = app(CurrentBook::class)->book;
        $route = $request->route();
        $id = $route instanceof Route ? $route->parameter(self::PARAMETER) : null;

        if ($route instanceof Route) {
            $route->forgetParameter(self::PARAMETER);
        }

        $rule = is_string($id) ? RecurringRule::inBook($id, $book) : null;
        Gate::authorize('access', [RecurringRule::class, $rule, $book]);

        app()->instance(RecurringRule::class, $rule);

        return $next($request);
    }
}
