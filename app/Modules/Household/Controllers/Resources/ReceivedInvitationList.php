<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Resources;

use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Support\ReceivedInvitationPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class ReceivedInvitationList extends ResourceCollection
{
    public function __construct(private readonly ReceivedInvitationPage $invitations)
    {
        parent::__construct($invitations->page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return array_values(array_map(
            fn (HouseholdInvitation $invitation): array => (new ReceivedInvitationResource(
                $invitation,
                $this->invitations->inviterNameOf($invitation),
            ))->toArray($request),
            $this->invitations->page->items(),
        ));
    }
}
