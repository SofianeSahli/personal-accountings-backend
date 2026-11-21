<?php

declare(strict_types=1);

namespace App\Modules\Household\Support;

use App\Modules\Household\Models\HouseholdInvitation;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class ReceivedInvitationPage
{
    /**
     * @param  CursorPaginator<int, HouseholdInvitation>  $page
     * @param  array<string, string>  $inviterNames  account id => name
     */
    public function __construct(
        public CursorPaginator $page,
        public array $inviterNames,
    ) {}

    public function inviterNameOf(HouseholdInvitation $invitation): string
    {
        return $this->inviterNames[$invitation->invited_by] ?? '';
    }
}
