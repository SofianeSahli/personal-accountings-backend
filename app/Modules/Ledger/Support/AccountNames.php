<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

final readonly class AccountNames
{
    /**
     * @param  array<string, string>  $names
     */
    public function __construct(private array $names) {}

    /**
     * @return array{id: string, name: ?string}
     */
    public function account(string $id): array
    {
        return ['id' => $id, 'name' => $this->names[$id] ?? null];
    }
}
