<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Controllers;

use App\Modules\Ledger\Actions\ListCategories;
use App\Modules\Ledger\Controllers\Resources\CategoryResource;
use App\Modules\Ledger\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoryController
{
    public function index(ListCategories $listCategories): AnonymousResourceCollection
    {
        $tops = $listCategories->handle()->map(fn (Category $top): CategoryResource => new CategoryResource($top));

        return CategoryResource::collection($tops);
    }
}
