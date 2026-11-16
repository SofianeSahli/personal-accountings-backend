<?php

declare(strict_types=1);

namespace App\Modules\Identity\Tests\Support;

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

final class IdentityScenario
{
    private const ME = '/api/v1/me';

    private const SOME_CODE = '111111';

    private const OTHER_CODE = '222222';

    public static function deviceId(): string
    {
        return (string) Str::uuid();
    }

    public static function tokenFor(User $user): string
    {
        return $user->createToken(self::deviceId())->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    public static function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public static function latestMailedCode(): string
    {
        $mail = Mail::queued(EmailCodeMail::class)->last();

        return $mail instanceof EmailCodeMail ? $mail->code : '';
    }

    public static function codeOtherThan(string $code): string
    {
        return $code === self::SOME_CODE ? self::OTHER_CODE : self::SOME_CODE;
    }

    public static function assertTokenWorks(string $token): void
    {
        Auth::forgetGuards();
        getJson(self::ME, self::bearer($token))->assertOk();
    }

    public static function assertTokenRefused(string $token): void
    {
        Auth::forgetGuards();
        getJson(self::ME, self::bearer($token))
            ->assertStatus(401)
            ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    }
}
