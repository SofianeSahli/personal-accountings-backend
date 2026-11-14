<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\Locale;

final readonly class GoogleSignUp
{
    public function __construct(
        public ?string $termsVersion,
        public ?string $phone,
        public Locale $locale,
    ) {}
}
