<?php

declare(strict_types=1);

namespace App\Modules\Household\Actions;

use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Household\Support\QrInvitationPreview;
use App\Modules\Identity\Actions\Accounts\FindAccountNames;

final class PreviewQrInvitation
{
    public function __construct(private readonly FindAccountNames $findAccountNames) {}

    public function handle(HouseholdQrInvitation $invitation): QrInvitationPreview
    {
        $names = $this->findAccountNames->handle([$invitation->invited_by]);

        return new QrInvitationPreview(
            $invitation->household,
            $names[$invitation->invited_by] ?? '',
            $invitation->expires_at,
        );
    }
}
