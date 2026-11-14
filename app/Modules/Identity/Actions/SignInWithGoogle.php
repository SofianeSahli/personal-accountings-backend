<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Google\GoogleIdentity;
use App\Modules\Identity\Google\GoogleIdentityChecker;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\GoogleSignUp;
use App\Modules\Identity\Support\SignInResult;
use App\Modules\Identity\Support\SignInTarget;
use App\Shared\Errors\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class SignInWithGoogle
{
    public function __construct(
        private readonly GoogleIdentityChecker $googleIdentityChecker,
        private readonly FinishSignIn $finishSignIn,
    ) {}

    public function handle(string $idToken, SignInTarget $target, GoogleSignUp $signUp): SignInResult
    {
        $identity = $this->googleIdentityChecker->verify($idToken);

        try {
            $user = $this->resolveAccount($identity, $signUp);
        } catch (UniqueConstraintViolationException) {
            $user = $this->resolveAccount($identity, $signUp);
        }

        return $this->finishSignIn->handle($user, $target);
    }

    private function resolveAccount(GoogleIdentity $identity, GoogleSignUp $signUp): User
    {
        $linkedAccount = User::query()->where('google_id', $identity->sub)->first();

        if ($linkedAccount !== null) {
            return $this->refreshName($linkedAccount, $identity);
        }

        if (User::query()->where('email', $identity->email)->doesntExist()) {
            return $this->createAccount($identity, $signUp);
        }

        return DB::transaction(fn (): User => $this->claimEmail($identity, $signUp));
    }

    private function claimEmail(GoogleIdentity $identity, GoogleSignUp $signUp): User
    {
        $match = User::query()->where('email', $identity->email)->lockForUpdate()->firstOrFail();

        if ($match->google_id !== null || ! $identity->isAuthoritativeForEmail()) {
            throw new ApiException('auth.google_email_conflict', Response::HTTP_CONFLICT);
        }

        if ($match->hasConfirmedEmail()) {
            $match->google_id = $identity->sub;

            return $this->refreshName($match, $identity);
        }

        $match->delete();

        return $this->createAccount($identity, $signUp);
    }

    private function createAccount(GoogleIdentity $identity, GoogleSignUp $signUp): User
    {
        if ($signUp->termsVersion === null) {
            throw new ApiException('auth.terms_required', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return User::query()->create([
            'name' => $identity->nameForNewAccount(),
            'email' => $identity->email,
            'email_verified_at' => now(),
            'google_id' => $identity->sub,
            'phone' => $signUp->phone,
            'locale' => $signUp->locale,
            'terms_version' => $signUp->termsVersion,
            'terms_accepted_at' => now(),
        ]);
    }

    private function refreshName(User $user, GoogleIdentity $identity): User
    {
        $user->name = $identity->name ?? $user->name;
        $user->save();

        return $user;
    }
}
