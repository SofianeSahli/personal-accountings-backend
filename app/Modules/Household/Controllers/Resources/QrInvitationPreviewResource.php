<?php

declare(strict_types=1);

namespace App\Modules\Household\Controllers\Resources;

use App\Modules\Household\Support\QrInvitationPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class QrInvitationPreviewResource extends JsonResource
{
    public function __construct(private readonly QrInvitationPreview $preview)
    {
        parent::__construct($preview);
    }

    /**
     * @return array{household: array{id: string, name: string}, invited_by: array{name: string}, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'household' => [
                'id' => $this->preview->household->id,
                'name' => $this->preview->household->name,
            ],
            'invited_by' => ['name' => $this->preview->inviterName],
            'expires_at' => $this->preview->expiresAt->toIso8601ZuluString(),
        ];
    }
}
