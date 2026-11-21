<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Support\ReceivedInvitationPage;
use App\Modules\Identity\Actions\Accounts\FindAccountNames;
use App\Modules\Identity\Models\User;

final class ListMyInvitations
{
    public const PER_PAGE = 25;

    public function __construct(private readonly FindAccountNames $findAccountNames) {}

    public function handle(User $user): ReceivedInvitationPage
    {
        $email = $user->verifiedEmail();
        $query = HouseholdInvitation::query()
            ->pending()
            ->with('household')
            ->orderBy('created_at')
            ->orderBy('id');

        $page = ($email === null ? $query->whereRaw('0 = 1') : $query->where('email', $email))
            ->cursorPaginate(self::PER_PAGE);

        $inviterIds = array_values(array_unique(array_map(
            fn (HouseholdInvitation $invitation): string => $invitation->invited_by,
            $page->items(),
        )));

        return new ReceivedInvitationPage($page, $this->findAccountNames->handle($inviterIds));
    }
}
