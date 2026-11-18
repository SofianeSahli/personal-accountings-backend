<?php

declare(strict_types=1);

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

/**
 * @param  array<string, mixed>  $attributes
 */
function resetAccount(array $attributes = []): User
{
    return User::factory()->create([
        'email' => 'amira@example.com',
        'password' => 'the old password',
        ...$attributes,
    ]);
}

function askResetCode(): string
{
    postJson('/api/v1/auth/password/forgot', ['email' => 'amira@example.com'])->assertStatus(202);

    return IdentityScenario::latestMailedCode();
}

function resetTo(string $password, string $code): void
{
    postJson('/api/v1/auth/password/reset', ['email' => 'amira@example.com', 'code' => $code, 'password' => $password])
        ->assertNoContent();
}

function signInWith(string $password): int
{
    return postJson('/api/v1/auth/email/login', [
        'email' => 'amira@example.com',
        'password' => $password,
        'device_id' => IdentityScenario::deviceId(),
    ])->status();
}

beforeEach(function (): void {
    Mail::fake();
});

it('REQ-3.1 answers identically for a known and an unknown address, mailing only the known one', function (): void {
    resetAccount();

    $known = postJson('/api/v1/auth/password/forgot', ['email' => 'amira@example.com']);
    $unknown = postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com']);

    $known->assertStatus(202);
    expect($unknown->status())->toBe($known->status());
    expect($unknown->getContent())->toBe($known->getContent());

    Mail::assertQueued(EmailCodeMail::class, 1);
    Mail::assertQueued(EmailCodeMail::class, fn (EmailCodeMail $mail): bool => $mail->hasTo('amira@example.com')
        && $mail->purpose === EmailCodePurpose::PasswordReset);
});

it('REQ-3.1 does a slow hash for an unknown address too', function (): void {
    $hash = Hash::spy();

    postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com'])->assertStatus(202);

    $hash->shouldHaveReceived('make')->once();
});

it('REQ-3.1 sends no reset code for an unconfirmed sign-up', function (): void {
    resetAccount(['email_verified_at' => null]);

    postJson('/api/v1/auth/password/forgot', ['email' => 'amira@example.com'])->assertStatus(202);

    Mail::assertNothingQueued();
});

it('REQ-3.2 sets the new password, ends every token and keeps two-step sign-in', function (): void {
    $user = resetAccount(['two_factor_enabled_at' => now()]);
    $phoneToken = IdentityScenario::tokenFor($user);
    IdentityScenario::tokenFor($user);

    resetTo('the new password', askResetCode());

    $user->refresh();
    expect(Hash::check('the new password', (string) $user->password))->toBeTrue();
    expect($user->tokens()->count())->toBe(0);
    expect($user->hasTwoFactor())->toBeTrue();
    IdentityScenario::assertTokenRefused($phoneToken);
});

it('REQ-3.2 lets the new password sign in, and not the old one', function (): void {
    resetAccount();

    resetTo('the new password', askResetCode());

    expect(signInWith('the old password'))->toBe(401);
    expect(signInWith('the new password'))->toBe(200);
});

it('REQ-3.2 lifts a password lock', function (): void {
    resetAccount(['password_failed_attempts' => 4, 'password_locked_until' => now()->addMinutes(10)]);

    resetTo('the new password', askResetCode());

    expect(signInWith('the new password'))->toBe(200);
});

it('REQ-3.4 gives a Google-only account a password through its mailbox', function (): void {
    resetAccount(['password' => null, 'google_id' => 'sub-1']);

    resetTo('the new password', askResetCode());

    expect(signInWith('the new password'))->toBe(200);
});

it('REQ-3.3 refuses wrong, expired, used and unknown codes', function (): void {
    $user = resetAccount();
    $code = askResetCode();
    $body = ['email' => 'amira@example.com', 'password' => 'the new password'];

    postJson('/api/v1/auth/password/reset', [...$body, 'code' => IdentityScenario::codeOtherThan($code)])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    postJson('/api/v1/auth/password/reset', [...$body, 'email' => 'nobody@example.com', 'code' => $code])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    postJson('/api/v1/auth/password/reset', [...$body, 'code' => $code])->assertNoContent();

    postJson('/api/v1/auth/password/reset', [...$body, 'code' => $code])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    $freshCode = askResetCode();
    travel(16)->minutes();

    postJson('/api/v1/auth/password/reset', [...$body, 'code' => $freshCode])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    expect($user->fresh())->not->toBeNull();
});

it('REQ-3.3 does not accept a confirmation code for a reset', function (): void {
    config(['identity.auth_test_mode' => true]);
    postJson('/api/v1/auth/email/register', [
        'name' => 'Amira',
        'email' => 'amira@example.com',
        'password' => 'long enough secret',
        'terms_version' => config('identity.terms_version'),
    ])->assertStatus(202);

    postJson('/api/v1/auth/password/reset', ['email' => 'amira@example.com', 'code' => '000000', 'password' => 'the new password'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('REQ-1.11 gives a code for each invalid field', function (): void {
    postJson('/api/v1/auth/password/forgot', [])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['email' => ['email.required']]]]);

    postJson('/api/v1/auth/password/reset', ['email' => 'amira@example.com', 'code' => '123', 'password' => 'short'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'code' => ['code.digits'],
            'password' => ['password.min'],
        ]]]);
});

it('REQ-8.2 limits forgot-password per address', function (): void {
    foreach (range(1, 3) as $attempt) {
        postJson('/api/v1/auth/password/forgot', ['email' => 'amira@example.com'])->assertStatus(202);
    }

    postJson('/api/v1/auth/password/forgot', ['email' => 'amira@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});
