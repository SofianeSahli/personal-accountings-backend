<?php

declare(strict_types=1);

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

function twoStepAccount(): User
{
    return User::factory()->withTwoFactor()->create([
        'email' => 'amira@gmail.com',
        'password' => 'the right password',
    ]);
}

/**
 * @return TestResponse<Response>
 */
function startPasswordSignIn(?string $deviceId = null): TestResponse
{
    return postJson('/api/v1/auth/email/login', [
        'email' => 'amira@gmail.com',
        'password' => 'the right password',
        'device_id' => $deviceId ?? IdentityScenario::deviceId(),
    ]);
}

function pendingSignIn(): string
{
    return (string) startPasswordSignIn()->assertOk()->json('data.pending_sign_in');
}

/**
 * @return TestResponse<Response>
 */
function sendSignInCode(string $pendingSignIn, string $code): TestResponse
{
    return postJson('/api/v1/auth/two-factor', ['pending_sign_in' => $pendingSignIn, 'code' => $code]);
}

/**
 * @return TestResponse<Response>
 */
function resendSignInCode(string $pendingSignIn): TestResponse
{
    return postJson('/api/v1/auth/two-factor/resend', ['pending_sign_in' => $pendingSignIn]);
}

/**
 * @param  TestResponse<Response>  $response
 */
function assertPendingRefused(TestResponse $response): void
{
    $response->assertStatus(401)->assertExactJson(['error' => ['code' => 'auth.pending_invalid']]);
}

beforeEach(function (): void {
    Mail::fake();
});

it('REQ-5.2 holds a password sign-in and emails a code to the account address', function (): void {
    $user = twoStepAccount();

    $response = startPasswordSignIn();

    $response->assertOk()
        ->assertJsonPath('data.status', 'code_required')
        ->assertJsonPath('data.expires_in_seconds', 600)
        ->assertJsonMissingPath('data.token')
        ->assertJsonMissingPath('data.account');
    expect($response->json('data.pending_sign_in'))->toBeString();
    expect($user->tokens()->count())->toBe(0);

    Mail::assertQueued(EmailCodeMail::class, fn (EmailCodeMail $mail): bool => $mail->hasTo('amira@gmail.com')
        && $mail->purpose === EmailCodePurpose::SignIn);
});

it('REQ-5.2 holds a Google sign-in until the emailed code', function (): void {
    $user = twoStepAccount();
    config(['identity.auth_test_mode' => true]);

    $response = postJson('/api/v1/auth/google', [
        'id_token' => 'test:amira@gmail.com',
        'device_id' => IdentityScenario::deviceId(),
    ]);

    $response->assertOk()->assertJsonPath('data.status', 'code_required');
    expect($user->tokens()->count())->toBe(0);
    Mail::assertQueued(EmailCodeMail::class, 1);

    sendSignInCode((string) $response->json('data.pending_sign_in'), '000000')
        ->assertOk()
        ->assertJsonPath('data.status', 'signed_in')
        ->assertJsonPath('data.account.id', $user->id);
});

it('REQ-5.2 holds an email confirmation until the emailed code', function (): void {
    $user = User::factory()->unverified()->withTwoFactor()->create(['email' => 'amira@gmail.com']);
    config(['identity.auth_test_mode' => true]);
    postJson('/api/v1/auth/email/resend', ['email' => 'amira@gmail.com'])->assertStatus(202);

    postJson('/api/v1/auth/email/confirm', ['email' => 'amira@gmail.com', 'code' => '000000', 'device_id' => IdentityScenario::deviceId()])
        ->assertOk()
        ->assertJsonPath('data.status', 'code_required');

    expect($user->fresh()?->hasConfirmedEmail())->toBeTrue();
    expect($user->tokens()->count())->toBe(0);
});

it('REQ-5.2 sends a 6-digit code', function (): void {
    twoStepAccount();

    pendingSignIn();

    expect(IdentityScenario::latestMailedCode())->toMatch('/^\d{6}$/');
});

it('REQ-5.7 stores only hashes and never returns the code', function (): void {
    twoStepAccount();

    $response = startPasswordSignIn();
    $code = IdentityScenario::latestMailedCode();
    $stored = PendingSignIn::query()->sole();

    expect($stored->token_hash)->toBe(hash('sha256', (string) $response->json('data.pending_sign_in')));
    expect($stored->code_hash)->not->toBe($code);
    expect(Hash::check($code, $stored->code_hash))->toBeTrue();
    expect($response->getContent())->not->toContain($code);
    expect($response->getContent())->not->toContain($stored->code_hash);
});

it('REQ-5.3 gives a token for the device named at the sign-in after the right code', function (): void {
    $user = twoStepAccount();
    $deviceId = IdentityScenario::deviceId();
    $pendingSignIn = (string) startPasswordSignIn($deviceId)->json('data.pending_sign_in');

    $response = sendSignInCode($pendingSignIn, IdentityScenario::latestMailedCode());

    $response->assertOk()->assertJsonPath('data.status', 'signed_in');
    expect($user->tokens()->sole()->name)->toBe($deviceId);
    expect(PendingSignIn::query()->count())->toBe(0);
    getJson('/api/v1/me', IdentityScenario::bearer((string) $response->json('data.token')))->assertOk();
});

it('REQ-5.4 answers a wrong code with code.invalid and keeps the sign-in waiting', function (): void {
    $user = twoStepAccount();
    $pendingSignIn = pendingSignIn();
    $code = IdentityScenario::latestMailedCode();

    sendSignInCode($pendingSignIn, IdentityScenario::codeOtherThan($code))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    expect($user->tokens()->count())->toBe(0);
    sendSignInCode($pendingSignIn, $code)->assertOk();
});

it('REQ-5.4 ends the sign-in at the fifth wrong code, even for the right code', function (): void {
    $user = twoStepAccount();
    $pendingSignIn = pendingSignIn();
    $code = IdentityScenario::latestMailedCode();

    foreach (range(1, 4) as $attempt) {
        sendSignInCode($pendingSignIn, IdentityScenario::codeOtherThan($code))->assertStatus(422);
    }

    assertPendingRefused(sendSignInCode($pendingSignIn, IdentityScenario::codeOtherThan($code)));
    assertPendingRefused(sendSignInCode($pendingSignIn, $code));

    expect(PendingSignIn::query()->count())->toBe(0);
    expect($user->tokens()->count())->toBe(0);
});

it('REQ-5.4 does not lock the account after 5 wrong codes', function (): void {
    twoStepAccount();
    $pendingSignIn = pendingSignIn();
    $code = IdentityScenario::latestMailedCode();

    foreach (range(1, 5) as $attempt) {
        sendSignInCode($pendingSignIn, IdentityScenario::codeOtherThan($code));
    }

    $freshSignIn = pendingSignIn();
    sendSignInCode($freshSignIn, IdentityScenario::latestMailedCode())->assertOk();
});

it('REQ-5.4 refuses a sign-in that is unknown, used or past 10 minutes', function (): void {
    twoStepAccount();
    assertPendingRefused(sendSignInCode('unknown', '123456'));

    $usedSignIn = pendingSignIn();
    $usedCode = IdentityScenario::latestMailedCode();
    sendSignInCode($usedSignIn, $usedCode)->assertOk();
    assertPendingRefused(sendSignInCode($usedSignIn, $usedCode));

    $expiredSignIn = pendingSignIn();
    $expiredCode = IdentityScenario::latestMailedCode();
    travel(10)->minutes();
    travel(1)->seconds();
    assertPendingRefused(sendSignInCode($expiredSignIn, $expiredCode));

    expect(PendingSignIn::query()->count())->toBe(0);
});

it('REQ-5.3 still accepts the code just before 10 minutes', function (): void {
    twoStepAccount();
    $pendingSignIn = pendingSignIn();
    $code = IdentityScenario::latestMailedCode();

    travel(9)->minutes();

    sendSignInCode($pendingSignIn, $code)->assertOk();
});

it('REQ-5.3 gives each sign-in its own code, which works only for that sign-in', function (): void {
    twoStepAccount();
    $firstSignIn = pendingSignIn();
    $firstCode = IdentityScenario::latestMailedCode();
    $secondSignIn = pendingSignIn();
    $secondCode = IdentityScenario::latestMailedCode();

    expect(PendingSignIn::query()->count())->toBe(2);

    sendSignInCode($firstSignIn, $secondCode)->assertStatus(422);
    sendSignInCode($secondSignIn, $firstCode)->assertStatus(422);

    sendSignInCode($firstSignIn, $firstCode)->assertOk();
    sendSignInCode($secondSignIn, $secondCode)->assertOk();
});

it('REQ-5.5 kills the previous code on a resend', function (): void {
    twoStepAccount();
    $pendingSignIn = pendingSignIn();
    $firstCode = IdentityScenario::latestMailedCode();

    resendSignInCode($pendingSignIn)->assertNoContent(202);
    $secondCode = IdentityScenario::latestMailedCode();

    Mail::assertQueued(EmailCodeMail::class, 2);
    sendSignInCode($pendingSignIn, $firstCode)->assertStatus(422);
    sendSignInCode($pendingSignIn, $secondCode)->assertOk();
});

it('REQ-5.5 restarts the 10 minutes and the wrong tries on a resend', function (): void {
    twoStepAccount();
    $pendingSignIn = pendingSignIn();
    $firstCode = IdentityScenario::latestMailedCode();

    foreach (range(1, 4) as $attempt) {
        sendSignInCode($pendingSignIn, IdentityScenario::codeOtherThan($firstCode))->assertStatus(422);
    }

    travel(8)->minutes();
    resendSignInCode($pendingSignIn)->assertStatus(202);
    travel(8)->minutes();

    $secondCode = IdentityScenario::latestMailedCode();
    sendSignInCode($pendingSignIn, IdentityScenario::codeOtherThan($secondCode))->assertStatus(422);
    sendSignInCode($pendingSignIn, $secondCode)->assertOk();
});

it('REQ-5.5 sends nothing for a resend on an unknown or expired sign-in', function (): void {
    twoStepAccount();
    assertPendingRefused(resendSignInCode('unknown'));

    $expiredSignIn = pendingSignIn();
    travel(10)->minutes();
    travel(1)->seconds();
    assertPendingRefused(resendSignInCode($expiredSignIn));

    Mail::assertQueued(EmailCodeMail::class, 1);
    expect(PendingSignIn::query()->count())->toBe(0);
});

it('REQ-8.2 limits resends per account, across sign-ins', function (): void {
    twoStepAccount();
    $firstSignIn = pendingSignIn();
    $secondSignIn = pendingSignIn();

    resendSignInCode($firstSignIn)->assertStatus(202);
    resendSignInCode($firstSignIn)->assertStatus(202);
    resendSignInCode($secondSignIn)->assertStatus(202);

    resendSignInCode($secondSignIn)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');

    $otherAccount = User::factory()->withTwoFactor()->create(['password' => 'the other password']);
    $otherSignIn = (string) postJson('/api/v1/auth/email/login', [
        'email' => $otherAccount->email,
        'password' => 'the other password',
        'device_id' => IdentityScenario::deviceId(),
    ])->json('data.pending_sign_in');

    resendSignInCode($otherSignIn)->assertStatus(202);
});

it('REQ-8.2 limits resends per network origin', function (): void {
    foreach (range(1, 10) as $attempt) {
        resendSignInCode('unknown')->assertStatus(401);
    }

    resendSignInCode('unknown')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});

it('REQ-8.1 limits code checks per network origin', function (): void {
    foreach (range(1, 30) as $attempt) {
        sendSignInCode('unknown', '123456')->assertStatus(401);
    }

    sendSignInCode('unknown', '123456')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});

it('REQ-5.2 signs in directly with no mail when two-step sign-in is off', function (): void {
    twoStepAccount()->forceFill(['two_factor_enabled_at' => null])->save();

    startPasswordSignIn()->assertOk()->assertJsonPath('data.status', 'signed_in');

    Mail::assertNothingQueued();
    expect(PendingSignIn::query()->count())->toBe(0);
});

it('REQ-5.2 keeps open sessions working while a sign-in waits', function (): void {
    $openToken = IdentityScenario::tokenFor(twoStepAccount());

    pendingSignIn();

    IdentityScenario::assertTokenWorks($openToken);
});

it('REQ-3.2 ends a waiting sign-in when the password is reset, and keeps two-step sign-in on', function (): void {
    $user = twoStepAccount();
    $pendingSignIn = pendingSignIn();
    $signInCode = IdentityScenario::latestMailedCode();

    postJson('/api/v1/auth/password/forgot', ['email' => 'amira@gmail.com'])->assertStatus(202);
    postJson('/api/v1/auth/password/reset', [
        'email' => 'amira@gmail.com',
        'code' => IdentityScenario::latestMailedCode(),
        'password' => 'the new password',
    ])->assertNoContent();

    assertPendingRefused(sendSignInCode($pendingSignIn, $signInCode));
    expect($user->fresh()?->hasTwoFactor())->toBeTrue();
});

it('REQ-1.11 gives a code for each invalid field', function (): void {
    postJson('/api/v1/auth/two-factor', ['code' => 'abc'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'pending_sign_in' => ['pending_sign_in.required'],
            'code' => ['code.digits'],
        ]]]);

    postJson('/api/v1/auth/two-factor/resend', [])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'pending_sign_in' => ['pending_sign_in.required'],
        ]]]);
});
