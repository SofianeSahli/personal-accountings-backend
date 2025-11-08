<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategorieResource;
use App\Services\CategorieService;
use Illuminate\Http\JsonResponse;

class CategoriesController extends Controller
{
    public function __construct(private CategorieService $categorieService) {}

    public function get(): JsonResponse
    {
        $allCategories = $this->categorieService->all();
        $incomes = $allCategories->filter(function ($element) {
            return $element->is_income === 1;
        });
        $expenses = $allCategories->filter(function ($element) {
            return $element->is_income === 0;
        });
        $collectionExpenses = CategorieResource::collection($expenses);
        $collectionIncome = CategorieResource::collection($incomes);

        return response()->json([
            'incomes' => $collectionIncome,
            'expenses' => $collectionExpenses,
        ]);
    }
}
