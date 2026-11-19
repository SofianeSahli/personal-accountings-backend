<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers\Middleware;

use App\Modules\Household\Actions\FindMembership;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Support\CurrentBook;
use App\Shared\Errors\ApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequireBook
{
    private const PARAMETER = 'book';

    public function __construct(private readonly FindMembership $findMembership) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $book = $this->bookOf($request);
        Gate::authorize('view', [Book::class, $book]);

        $user = $request->user();

        if ($book === null || ! $user instanceof User) {
            throw new ApiException('book.not_found', Response::HTTP_NOT_FOUND);
        }

        app()->instance(CurrentBook::class, new CurrentBook(
            $book,
            $book->canBeManagedBy($user, $this->findMembership->handle($user)),
        ));

        return $next($request);
    }

    private function bookOf(Request $request): ?Book
    {
        $route = $request->route();
        $id = $route instanceof Route ? $route->parameter(self::PARAMETER) : null;

        if ($route instanceof Route) {
            $route->forgetParameter(self::PARAMETER);
        }

        return is_string($id) && Str::isUuid($id) ? Book::query()->find($id) : null;
    }
}
