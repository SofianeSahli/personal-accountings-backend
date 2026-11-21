<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdInvitation;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListHouseholdInvitations
{
    public const PER_PAGE = 25;

    /**
     * @return CursorPaginator<int, HouseholdInvitation>
     */
    public function handle(Household $household): CursorPaginator
    {
        return HouseholdInvitation::query()
            ->pending()
            ->where('household_id', $household->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
