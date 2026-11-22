<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Category;
use Illuminate\Database\Eloquent\Collection;

final class ListCategories
{
    /**
     * @return Collection<int, Category>
     */
    public function handle(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->orderBy('position')
            ->with('children.children')
            ->get();
    }
}
