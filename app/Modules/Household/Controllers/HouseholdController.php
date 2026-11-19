<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers;

use App\Modules\Household\Actions\CreateHousehold;
use App\Modules\Household\Actions\DeleteHousehold;
use App\Modules\Household\Actions\ListHouseholds;
use App\Modules\Household\Actions\RenameHousehold;
use App\Modules\Household\Controllers\Requests\CreateHouseholdRequest;
use App\Modules\Household\Controllers\Requests\UpdateHouseholdRequest;
use App\Modules\Household\Controllers\Resources\HouseholdList;
use App\Modules\Household\Controllers\Resources\HouseholdResource;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Household\Support\CurrentHousehold;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class HouseholdController
{
    public function index(#[CurrentUser] User $user, ListHouseholds $listHouseholds): HouseholdList
    {
        return new HouseholdList($listHouseholds->handle($user));
    }

    public function store(CreateHouseholdRequest $request, #[CurrentUser] User $user, CreateHousehold $createHousehold): JsonResponse
    {
        $household = $createHousehold->handle($user, $request->householdName());

        return (new HouseholdResource($household, HouseholdRole::Owner))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(CurrentHousehold $current): HouseholdResource
    {
        return new HouseholdResource($current->household, $current->role);
    }

    public function update(UpdateHouseholdRequest $request, CurrentHousehold $current, RenameHousehold $renameHousehold): HouseholdResource
    {
        $household = $renameHousehold->handle($current->household, $request->householdName());

        return new HouseholdResource($household, $current->role);
    }

    public function destroy(CurrentHousehold $current, DeleteHousehold $deleteHousehold): Response
    {
        Gate::authorize('delete', [Household::class, $current->household]);

        $deleteHousehold->handle($current->household);

        return response()->noContent();
    }
}
