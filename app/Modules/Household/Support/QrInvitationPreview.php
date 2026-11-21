<?php

declare(strict_types=1);

namespace App\Modules\Household\Support;

use App\Modules\Household\Models\Household;
use Carbon\CarbonInterface;

final readonly class QrInvitationPreview
{
    public function __construct(
        public Household $household,
        public string $inviterName,
        public CarbonInterface $expiresAt,
    ) {}
}
