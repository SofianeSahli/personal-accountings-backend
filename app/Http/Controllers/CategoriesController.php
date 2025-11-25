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

        return response()->json(CategorieResource::collection($allCategories));
    }
}
