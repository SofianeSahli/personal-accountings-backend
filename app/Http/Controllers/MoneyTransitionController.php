<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoneyTransitionRequest;
use App\Http\Resources\MoneyTransition as ResourcesMoneyTransition;
use App\Models\MoneyTransition;
use App\Pagination\CustomLengthAwarePaginator;
use App\Services\MoneyTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoneyTransitionController extends Controller
{
    public function __construct(private MoneyTransitionService $moneyTransition)
    {
    }

    public function create(MoneyTransitionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $moneyTransition = $this->moneyTransition->create($data);
        if ($moneyTransition instanceof \Illuminate\Database\Eloquent\Collection) {
            return response()->json(ResourcesMoneyTransition::collection($moneyTransition), 201);
        } else {
            return response()->json(new ResourcesMoneyTransition($moneyTransition), 201);
        }
    }

    public function update(MoneyTransitionRequest $request, MoneyTransition $moneyTransition): JsonResponse
    {
        $data = $request->validated();
        $updatedVersion = $this->moneyTransition->update($moneyTransition->id, $data);

        return response()->json(new ResourcesMoneyTransition($updatedVersion));
    }

    public function query(Request $request)
    {
        $filters = $request->only([
            'amount',
            'categorie_id',
            'user_id',
            'categorie.is_income',
            'categorie.parent_id',
            'created_at',
            'categories',
            'start_date',
            'end_date',
            'max_amount',
            'type',
            'min_amount',
        ]);
        $perPage = $request->query('per_page');
        $paginated = $this->moneyTransition->query($filters, $perPage ?? 10);
        $customPaginated = new CustomLengthAwarePaginator(
            ResourcesMoneyTransition::collection($paginated->items()),
            $paginated->total(),
            $paginated->perPage(),
            $paginated->currentPage(),
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json($customPaginated->toArray(), 200);
    }

    public function summary(Request $request)
    {

        $filters = $request->only([
        'user_id',
        'start_date',
        'end_date',
        ]);
        $result =  $this->moneyTransition->summary($filters);
        return response()->json($result);
    }
}
