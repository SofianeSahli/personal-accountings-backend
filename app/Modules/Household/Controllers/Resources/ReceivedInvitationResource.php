<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Resources;

use App\Modules\Household\Models\HouseholdInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReceivedInvitationResource extends JsonResource
{
    public function __construct(
        private readonly HouseholdInvitation $invitation,
        private readonly string $inviterName,
    ) {
        parent::__construct($invitation);
    }

    /**
     * @return array{id: string, household: array{id: string, name: string}, invited_by: array{name: string}, created_at: ?string, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->invitation->id,
            'household' => [
                'id' => $this->invitation->household->id,
                'name' => $this->invitation->household->name,
            ],
            'invited_by' => ['name' => $this->inviterName],
            'created_at' => $this->invitation->created_at?->toIso8601ZuluString(),
            'expires_at' => $this->invitation->expires_at->toIso8601ZuluString(),
        ];
    }
}
