<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCode;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class SendEmailCode
{
    public function __construct(private readonly GenerateEmailCode $generateEmailCode) {}

    public function handle(User $user, EmailCodePurpose $purpose): void
    {
        $code = $this->generateEmailCode->handle();

        EmailCode::query()->upsert(
            [[
                'id' => (string) Str::uuid7(),
                'user_id' => $user->id,
                'purpose' => $purpose->value,
                'code_hash' => Hash::make($code),
                'failed_attempts' => 0,
                'expires_at' => now()->addMinutes(EmailCode::VALID_MINUTES),
            ]],
            ['user_id', 'purpose'],
            ['code_hash', 'failed_attempts', 'expires_at'],
        );

        Mail::to($user->email)->locale($user->locale->value)->queue(new EmailCodeMail($code, $purpose));
    }
}
