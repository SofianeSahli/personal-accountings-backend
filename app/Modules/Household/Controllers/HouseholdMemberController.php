<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers;

use App\Modules\Household\Actions\ListMembers;
use App\Modules\Household\Controllers\Resources\MemberList;
use App\Modules\Household\Support\CurrentHousehold;

final class HouseholdMemberController
{
    public function index(CurrentHousehold $current, ListMembers $listMembers): MemberList
    {
        return new MemberList($listMembers->handle($current->household));
    }
}
