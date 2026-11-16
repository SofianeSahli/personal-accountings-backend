<?php

declare(strict_types=1);

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCode;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\json;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

/**
 * @return array{User, string}
 */
function twoFactorAccount(bool $enabled = true): array
{
    $factory = User::factory();
    $user = ($enabled ? $factory->withTwoFactor() : $factory)->create();

    return [$user, IdentityScenario::tokenFor($user)];
}

function askDisableCode(string $token): string
{
    postJson('/api/v1/me/two-factor/disable-code', [], IdentityScenario::bearer($token))->assertStatus(202);

    return IdentityScenario::latestMailedCode();
}

/**
 * @return TestResponse<Response>
 */
function turnOffWith(string $token, string $code): TestResponse
{
    Auth::forgetGuards();

    return deleteJson('/api/v1/me/two-factor', ['code' => $code], IdentityScenario::bearer($token));
}

function assertCodeRefused(string $token, string $code): void
{
    turnOffWith($token, $code)
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
}

beforeEach(function (): void {
    Mail::fake();
});

it('REQ-7.1 shows two-step sign-in off by default', function (): void {
    [, $token] = twoFactorAccount(enabled: false);

    getJson('/api/v1/me', IdentityScenario::bearer($token))->assertJsonPath('data.two_factor_enabled', false);
});

it('REQ-5.1 turns on and keeps devices signed in', function (): void {
    [$user, $token] = twoFactorAccount(enabled: false);

    postJson('/api/v1/me/two-factor', [], IdentityScenario::bearer($token))->assertNoContent();

    expect($user->fresh()?->hasTwoFactor())->toBeTrue();
    Auth::forgetGuards();
    getJson('/api/v1/me', IdentityScenario::bearer($token))->assertOk()->assertJsonPath('data.two_factor_enabled', true);
});

it('REQ-5.1 refuses to turn on twice', function (): void {
    [, $token] = twoFactorAccount();

    postJson('/api/v1/me/two-factor', [], IdentityScenario::bearer($token))
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'two_factor.already_enabled']]);
});

it('REQ-5.6 emails a disable code to the account address and stores only its hash', function (): void {
    [$user, $token] = twoFactorAccount();

    postJson('/api/v1/me/two-factor/disable-code', [], IdentityScenario::bearer($token))->assertNoContent(202);

    Mail::assertQueued(EmailCodeMail::class, fn (EmailCodeMail $mail): bool => $mail->hasTo($user->email)
        && $mail->purpose === EmailCodePurpose::TwoFactorDisable);

    $stored = EmailCode::query()->sole();
    expect($stored->purpose)->toBe(EmailCodePurpose::TwoFactorDisable);
    expect(Hash::check(IdentityScenario::latestMailedCode(), $stored->code_hash))->toBeTrue();
});

it('REQ-5.6 refuses a disable code when two-step sign-in is off', function (): void {
    [, $token] = twoFactorAccount(enabled: false);

    postJson('/api/v1/me/two-factor/disable-code', [], IdentityScenario::bearer($token))
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'two_factor.not_enabled']]);

    Mail::assertNothingQueued();
});

it('REQ-5.6 turns off with the right code and ends no session', function (): void {
    [$user, $token] = twoFactorAccount();
    $otherToken = IdentityScenario::tokenFor($user);

    turnOffWith($token, askDisableCode($token))->assertNoContent();

    expect($user->fresh()?->hasTwoFactor())->toBeFalse();
    expect(EmailCode::query()->count())->toBe(0);
    IdentityScenario::assertTokenWorks($token);
    IdentityScenario::assertTokenWorks($otherToken);
});

it('REQ-5.6 stays on with a wrong, expired or missing code', function (): void {
    [$user, $token] = twoFactorAccount();

    assertCodeRefused($token, '123456');

    $code = askDisableCode($token);
    assertCodeRefused($token, IdentityScenario::codeOtherThan($code));

    travel(15)->minutes();
    travel(1)->seconds();
    assertCodeRefused($token, $code);

    expect($user->fresh()?->hasTwoFactor())->toBeTrue();
});

it('REQ-5.6 refuses a code that was already used', function (): void {
    [$user, $token] = twoFactorAccount();
    $code = askDisableCode($token);

    turnOffWith($token, $code)->assertNoContent();
    $user->fresh()?->forceFill(['two_factor_enabled_at' => now()])->save();

    assertCodeRefused($token, $code);
});

it('REQ-1.7 kills the disable code after 5 wrong tries', function (): void {
    [$user, $token] = twoFactorAccount();
    $code = askDisableCode($token);

    foreach (range(1, 5) as $attempt) {
        turnOffWith($token, IdentityScenario::codeOtherThan($code))->assertStatus(422);
    }

    assertCodeRefused($token, $code);
    expect($user->fresh()?->hasTwoFactor())->toBeTrue();
});

it('REQ-5.6 does not take a code of another purpose or another account', function (): void {
    [$user, $token] = twoFactorAccount();

    postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertStatus(202);
    assertCodeRefused($token, IdentityScenario::latestMailedCode());

    $disableCode = askDisableCode($token);
    [, $otherToken] = twoFactorAccount();

    assertCodeRefused($otherToken, $disableCode);
});

it('REQ-5.6 refuses to turn off when two-step sign-in is off', function (): void {
    [, $token] = twoFactorAccount(enabled: false);

    turnOffWith($token, '123456')
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'two_factor.not_enabled']]);
});

it('REQ-1.11 gives a code for an invalid field', function (array $body, string $fieldCode): void {
    [, $token] = twoFactorAccount();

    deleteJson('/api/v1/me/two-factor', $body, IdentityScenario::bearer($token))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['code' => [$fieldCode]]]]);
})->with([
    [[], 'code.required'],
    [['code' => '12345'], 'code.digits'],
    [['code' => 123456], 'code.string'],
]);

it('REQ-8.2 limits disable codes per account and leaves other accounts alone', function (): void {
    [, $token] = twoFactorAccount();

    foreach (range(1, 3) as $attempt) {
        postJson('/api/v1/me/two-factor/disable-code', [], IdentityScenario::bearer($token))->assertStatus(202);
    }

    postJson('/api/v1/me/two-factor/disable-code', ['email' => 'any@example.com'], IdentityScenario::bearer($token))
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');

    [, $otherToken] = twoFactorAccount();
    Auth::forgetGuards();
    postJson('/api/v1/me/two-factor/disable-code', [], IdentityScenario::bearer($otherToken))->assertStatus(202);
});

it('REQ-6.7 needs a session', function (string $method, string $path): void {
    json($method, $path)
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
})->with([
    ['POST', '/api/v1/me/two-factor'],
    ['POST', '/api/v1/me/two-factor/disable-code'],
    ['DELETE', '/api/v1/me/two-factor'],
]);
