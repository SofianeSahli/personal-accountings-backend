<?php

declare(strict_types=1);

namespace App\Modules\Household\Support;

use App\Modules\Household\Models\HouseholdQrInvitation;

final readonly class NewQrInvitation
{
    public function __construct(
        public HouseholdQrInvitation $invitation,
        public string $token,
    ) {}
}
