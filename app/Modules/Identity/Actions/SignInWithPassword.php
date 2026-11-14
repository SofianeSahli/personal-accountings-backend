<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\SignInResult;
use App\Modules\Identity\Support\SignInTarget;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

final class SignInWithPassword
{
    public function __construct(
        private readonly CheckPassword $checkPassword,
        private readonly FinishSignIn $finishSignIn,
    ) {}

    public function handle(string $email, string $password, SignInTarget $target): SignInResult
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            Hash::check($password, CheckPassword::DUMMY_HASH);

            throw $this->invalidCredentials();
        }

        if (! $this->checkPassword->handle($user, $password)) {
            throw $this->invalidCredentials();
        }

        if (! $user->hasConfirmedEmail()) {
            throw new ApiException('auth.email_unconfirmed', Response::HTTP_FORBIDDEN);
        }

        return $this->finishSignIn->handle($user, $target);
    }

    private function invalidCredentials(): ApiException
    {
        return new ApiException('auth.invalid_credentials', Response::HTTP_UNAUTHORIZED);
    }
}
