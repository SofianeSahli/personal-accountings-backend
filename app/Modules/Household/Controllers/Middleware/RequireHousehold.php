<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Middleware;

use App\Modules\Household\Actions\FindMembership;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Household\Support\CurrentHousehold;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequireHousehold
{
    private const PARAMETER = 'household';

    public function __construct(private readonly FindMembership $findMembership) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $household = $this->householdOf($request);
        Gate::authorize('view', [Household::class, $household]);

        $user = $request->user();
        $membership = $user instanceof User ? $this->findMembership->handle($user) : null;

        if ($household === null || $membership === null) {
            throw new ApiException('household.not_found', Response::HTTP_NOT_FOUND);
        }

        app()->instance(CurrentHousehold::class, new CurrentHousehold(
            $household,
            $membership->isOwner ? HouseholdRole::Owner : HouseholdRole::Member,
        ));

        return $next($request);
    }

    private function householdOf(Request $request): ?Household
    {
        $route = $request->route();
        $id = $route instanceof Route ? $route->parameter(self::PARAMETER) : null;

        if ($route instanceof Route) {
            $route->forgetParameter(self::PARAMETER);
        }

        return is_string($id) && Str::isUuid($id) ? Household::query()->find($id) : null;
    }
}
