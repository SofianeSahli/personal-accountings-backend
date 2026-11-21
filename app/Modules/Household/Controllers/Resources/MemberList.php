<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Resources;

use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Support\MemberPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class MemberList extends ResourceCollection
{
    public function __construct(private readonly MemberPage $members)
    {
        parent::__construct($members->page);
    }

    /**
     * @return list<array{id: string, name: string, role: string}>
     */
    public function toArray(Request $request): array
    {
        return array_values(array_map(
            fn (HouseholdMember $member): array => (new MemberResource($member, $this->members->nameOf($member)))
                ->toArray($request),
            $this->members->page->items(),
        ));
    }
}
