<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Support\MemberPage;
use App\Modules\Identity\Actions\Accounts\FindAccountNames;

final class ListMembers
{
    public const PER_PAGE = 25;

    public function __construct(private readonly FindAccountNames $findAccountNames) {}

    public function handle(Household $household): MemberPage
    {
        $page = HouseholdMember::query()
            ->where('household_id', $household->id)
            ->orderByDesc('role')
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);

        $accountIds = array_values(array_map(
            fn (HouseholdMember $member): string => $member->user_id,
            $page->items(),
        ));

        return new MemberPage($page, $this->findAccountNames->handle($accountIds));
    }
}
