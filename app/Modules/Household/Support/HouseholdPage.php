<?php

declare(strict_types=1);

namespace App\Modules\Household\Support;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdRole;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class HouseholdPage
{
    /**
     * @param  CursorPaginator<int, Household>  $page
     */
    public function __construct(
        public CursorPaginator $page,
        public ?Membership $membership,
    ) {}

    public function roleIn(Household $household): HouseholdRole
    {
        return $this->membership?->householdId === $household->id && $this->membership->isOwner
            ? HouseholdRole::Owner
            : HouseholdRole::Member;
    }
}
