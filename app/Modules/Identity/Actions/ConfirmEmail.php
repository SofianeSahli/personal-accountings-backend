<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\SignInResult;
use App\Modules\Identity\Support\SignInTarget;

final class ConfirmEmail
{
    public function __construct(
        private readonly ConsumeEmailCode $consumeEmailCode,
        private readonly FinishSignIn $finishSignIn,
    ) {}

    public function handle(string $email, string $code, SignInTarget $target): SignInResult
    {
        $user = $this->consumeEmailCode->handle(
            User::query()->where('email', $email)->first(),
            EmailCodePurpose::EmailConfirmation,
            $code,
        );

        $user->email_verified_at = now();
        $user->save();

        return $this->finishSignIn->handle($user, $target);
    }
}
