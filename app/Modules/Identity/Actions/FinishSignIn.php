<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\SignInResult;
use App\Modules\Identity\Support\SignInTarget;

final class FinishSignIn
{
    private const PENDING_TOKEN_BYTES = 40;

    public function __construct(
        private readonly OpenSession $openSession,
        private readonly SendSignInCode $sendSignInCode,
    ) {}

    public function handle(User $user, SignInTarget $target): SignInResult
    {
        if ($user->hasTwoFactor()) {
            return $this->holdForCode($user, $target);
        }

        return $this->openSession->handle($user, $target);
    }

    private function holdForCode(User $user, SignInTarget $target): SignInResult
    {
        $pendingToken = bin2hex(random_bytes(self::PENDING_TOKEN_BYTES));

        $this->sendSignInCode->handle($user, new PendingSignIn([
            'user_id' => $user->id,
            'device_id' => $target->deviceId,
            'token_hash' => PendingSignIn::hashToken($pendingToken),
        ]));

        return SignInResult::codeRequired($pendingToken, PendingSignIn::VALID_SECONDS);
    }
}
