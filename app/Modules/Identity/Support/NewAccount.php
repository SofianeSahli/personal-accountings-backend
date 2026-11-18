<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\Locale;

final readonly class NewAccount
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $termsVersion,
        public ?string $phone,
        public Locale $locale,
    ) {}
}
