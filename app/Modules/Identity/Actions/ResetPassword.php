<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class ResetPassword
{
    private const SESSIONS_TABLE = 'sessions';

    public function __construct(private readonly ConsumeEmailCode $consumeEmailCode) {}

    public function handle(string $email, string $code, string $password): void
    {
        $user = $this->consumeEmailCode->handle(
            User::query()->where('email', $email)->first(),
            EmailCodePurpose::PasswordReset,
            $code,
        );

        DB::transaction(function () use ($user, $password): void {
            $user->password = $password;
            $user->password_failed_attempts = 0;
            $user->password_locked_until = null;
            $user->save();

            $user->tokens()->delete();
            DB::table(self::SESSIONS_TABLE)->where('user_id', $user->id)->delete();
            PendingSignIn::query()->where('user_id', $user->id)->delete();
        });
    }
}
