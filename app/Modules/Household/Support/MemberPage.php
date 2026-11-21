<?php

declare(strict_types=1);

namespace App\Modules\Household\Support;

use App\Modules\Household\Models\HouseholdMember;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class MemberPage
{
    /**
     * @param  CursorPaginator<int, HouseholdMember>  $page
     * @param  array<string, string>  $names  account id => name
     */
    public function __construct(
        public CursorPaginator $page,
        public array $names,
    ) {}

    public function nameOf(HouseholdMember $member): string
    {
        return $this->names[$member->user_id] ?? '';
    }
}
