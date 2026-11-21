<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Resources;

use App\Modules\Household\Models\HouseholdInvitation;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class InvitationList extends ResourceCollection
{
    /**
     * @param  CursorPaginator<int, HouseholdInvitation>  $invitations
     */
    public function __construct(private readonly CursorPaginator $invitations)
    {
        parent::__construct($invitations);
    }

    /**
     * @return list<array{id: string, email: string, created_at: ?string, expires_at: string}>
     */
    public function toArray(Request $request): array
    {
        return array_values(array_map(
            fn (HouseholdInvitation $invitation): array => (new InvitationResource($invitation))->toArray($request),
            $this->invitations->items(),
        ));
    }
}
