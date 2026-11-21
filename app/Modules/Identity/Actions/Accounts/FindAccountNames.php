<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions\Accounts;

use App\Modules\Identity\Models\User;

final class FindAccountNames
{
    /**
     * @param  list<string>  $accountIds
     * @return array<string, string>
     */
    public function handle(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        /** @var array<string, string> $names */
        $names = User::query()->whereIn('id', $accountIds)->pluck('name', 'id')->all();

        return $names;
    }
}
