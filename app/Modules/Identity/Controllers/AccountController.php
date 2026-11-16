<?php

declare(strict_types=1);

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Controllers\Resources\AccountResource;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

final class AccountController
{
    public function show(#[CurrentUser] User $user): AccountResource
    {
        return new AccountResource($user);
    }
}
