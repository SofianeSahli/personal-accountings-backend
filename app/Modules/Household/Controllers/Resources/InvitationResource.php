<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Resources;

use App\Modules\Household\Models\HouseholdInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InvitationResource extends JsonResource
{
    public function __construct(private readonly HouseholdInvitation $invitation)
    {
        parent::__construct($invitation);
    }

    /**
     * @return array{id: string, email: string, created_at: ?string, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'created_at' => $this->invitation->created_at?->toIso8601ZuluString(),
            'expires_at' => $this->invitation->expires_at->toIso8601ZuluString(),
        ];
    }
}
