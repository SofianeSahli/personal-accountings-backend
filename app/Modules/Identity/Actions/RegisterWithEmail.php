<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\NewAccount;
use App\Shared\Errors\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class RegisterWithEmail
{
    public function __construct(private readonly SendEmailCode $sendEmailCode) {}

    public function handle(NewAccount $account): void
    {
        try {
            $user = $this->saveUnconfirmedAccount($account);
        } catch (UniqueConstraintViolationException) {
            $user = $this->saveUnconfirmedAccount($account);
        }

        $this->sendEmailCode->handle($user, EmailCodePurpose::EmailConfirmation);
    }

    private function saveUnconfirmedAccount(NewAccount $account): User
    {
        $signUp = [
            'name' => $account->name,
            'email' => $account->email,
            'password' => $account->password,
            'phone' => $account->phone,
            'locale' => $account->locale,
            'terms_version' => $account->termsVersion,
            'terms_accepted_at' => now(),
        ];

        return DB::transaction(function () use ($signUp, $account): User {
            $existing = User::query()->where('email', $account->email)->lockForUpdate()->first();

            if ($existing === null) {
                return User::query()->create($signUp);
            }

            if ($existing->hasConfirmedEmail()) {
                throw new ApiException('email.taken', Response::HTTP_CONFLICT);
            }

            $existing->update($signUp);

            return $existing;
        });
    }
}
