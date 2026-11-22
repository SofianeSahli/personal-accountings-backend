<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Middleware;

use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Support\CurrentBook;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class RequireTransaction
{
    private const PARAMETER = 'transaction';

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

        $transaction = is_string($id) ? Transaction::inBook($id, $book) : null;
        Gate::authorize('access', [Transaction::class, $transaction, $book]);

        app()->instance(Transaction::class, $transaction);

        return $next($request);
    }
}
