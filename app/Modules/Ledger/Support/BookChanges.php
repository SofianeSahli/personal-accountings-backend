<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class BookChanges
{
    public function __construct(
        public ?string $name,
        public ?string $timezone,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toAttributes(): array
    {
        return array_filter(
            ['name' => $this->name, 'timezone' => $this->timezone],
            fn (?string $value): bool => $value !== null,
        );
    }
}
