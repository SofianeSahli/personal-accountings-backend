<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Resources;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Support\HouseholdPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class HouseholdList extends ResourceCollection
{
    public function __construct(private readonly HouseholdPage $households)
    {
        parent::__construct($households->page);
    }

    /**
     * @return list<array{id: string, name: string, role: string}>
     */
    public function toArray(Request $request): array
    {
        return array_values(array_map(
            fn (Household $household): array => (new HouseholdResource($household, $this->households->roleIn($household)))
                ->toArray($request),
            $this->households->page->items(),
        ));
    }
}
