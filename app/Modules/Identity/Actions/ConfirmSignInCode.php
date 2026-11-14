<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Support\SignInMode;
use App\Modules\Identity\Support\SignInResult;
use App\Modules\Identity\Support\SignInTarget;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

final class ConfirmSignInCode
{
    public function __construct(
        private readonly LockLivePendingSignIn $lockLivePendingSignIn,
        private readonly OpenSession $openSession,
    ) {}

    public function handle(string $pendingToken, string $code, SignInMode $mode): SignInResult
    {
        $outcome = DB::transaction(fn (): SignInResult|ApiException => $this->checkAndSpend($pendingToken, $code, $mode));

        if ($outcome instanceof ApiException) {
            throw $outcome;
        }

        return $outcome;
    }

    private function checkAndSpend(string $pendingToken, string $code, SignInMode $mode): SignInResult|ApiException
    {
        $pendingSignIn = $this->lockLivePendingSignIn->handle($pendingToken);

        if ($pendingSignIn === null) {
            return $this->signInEnded();
        }

        $target = SignInTarget::ofPendingSignIn($pendingSignIn->device_id);

        if ($target->mode !== $mode) {
            return $this->signInEnded();
        }

        if (! Hash::check($code, $pendingSignIn->code_hash)) {
            return $this->countWrongTry($pendingSignIn);
        }

        $pendingSignIn->delete();

        return $this->openSession->handle($pendingSignIn->user, $target);
    }

    private function countWrongTry(PendingSignIn $pendingSignIn): ApiException
    {
        $pendingSignIn->failed_attempts++;

        if ($pendingSignIn->failed_attempts >= PendingSignIn::MAX_FAILED_ATTEMPTS) {
            $pendingSignIn->delete();

            return $this->signInEnded();
        }

        $pendingSignIn->save();

        return new ApiException('code.invalid', Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function signInEnded(): ApiException
    {
        return new ApiException('auth.pending_invalid', Response::HTTP_UNAUTHORIZED);
    }
}
