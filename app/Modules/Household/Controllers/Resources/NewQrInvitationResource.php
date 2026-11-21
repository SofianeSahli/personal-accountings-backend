<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Resources;

use App\Modules\Household\Support\NewQrInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NewQrInvitationResource extends JsonResource
{
    public function __construct(private readonly NewQrInvitation $created)
    {
        parent::__construct($created->invitation);
    }

    /**
     * @return array{id: string, token: string, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->created->invitation->id,
            'token' => $this->created->token,
            'expires_at' => $this->created->invitation->expires_at->toIso8601ZuluString(),
        ];
    }
}
