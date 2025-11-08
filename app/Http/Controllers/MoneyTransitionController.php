<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoneyTransitionRequest;
use App\Http\Resources\MoneyTransition as ResourcesMoneyTransition;
use App\Models\MoneyTransition;
use App\Services\MoneyTransitionService;
use Illuminate\Http\JsonResponse;

class MoneyTransitionController extends Controller
{
    public function __construct(private MoneyTransitionService $moneyTransition) {}

    public function create(MoneyTransitionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->moneyTransition->create($data);

        return response()->json($user, 201);
    }

    public function update(MoneyTransitionRequest $request, MoneyTransition $moneyTransition): JsonResponse
    {
        $data = $request->validated();
        $updatedVersion = $this->moneyTransition->update($moneyTransition->id, $data);

        return response()->json(new ResourcesMoneyTransition($updatedVersion));
    }
}
