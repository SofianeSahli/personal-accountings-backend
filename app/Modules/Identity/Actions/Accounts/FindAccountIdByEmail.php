<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions\Accounts;

use App\Modules\Identity\Models\User;

final class FindAccountIdByEmail
{
    public function handle(string $email): ?string
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        $account = User::query()
            ->where('email', $email)
            ->get(['id', 'email'])
            ->first(fn (User $user): bool => mb_strtolower($user->email) === $email);

        return $account?->id;
    }
}
