<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\Emails\CheckPassword;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

/**
 * @return array<string, string>
 */
function passwordLoginBody(string $password, string $email = 'amira@example.com'): array
{
    return ['email' => $email, 'password' => $password, 'device_id' => IdentityScenario::deviceId()];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function passwordAccount(array $attributes = []): User
{
    return User::factory()->create([
        'email' => 'amira@example.com',
        'password' => 'the right password',
        ...$attributes,
    ]);
}

it('REQ-2.1 gives a session for the right password', function (): void {
    $user = passwordAccount();

    $response = postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'));

    $response->assertOk()
        ->assertJsonPath('data.status', 'signed_in')
        ->assertJsonPath('data.account.id', $user->id);

    getJson('/api/v1/me', IdentityScenario::bearer((string) $response->json('data.token')))->assertOk();
});

it('REQ-2.6 compares the address in lowercase', function (): void {
    passwordAccount();

    postJson('/api/v1/auth/email/login', passwordLoginBody('the right password', 'AMIRA@Example.com'))->assertOk();
});

it('REQ-2.3 keeps an unconfirmed address out, even with the right password', function (): void {
    passwordAccount(['email_verified_at' => null]);

    postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'))
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'auth.email_unconfirmed']]);
});

it('REQ-2.2 answers a wrong password and an unknown address identically', function (): void {
    passwordAccount();

    $wrongPassword = postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'));
    $unknownAddress = postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password', 'nobody@example.com'));

    $wrongPassword->assertStatus(401)->assertExactJson(['error' => ['code' => 'auth.invalid_credentials']]);
    expect($unknownAddress->status())->toBe($wrongPassword->status());
    expect($unknownAddress->getContent())->toBe($wrongPassword->getContent());
});

it('REQ-2.2 checks the dummy hash for an unknown address', function (): void {
    $hash = Hash::spy();

    postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password', 'nobody@example.com'))
        ->assertStatus(401);

    $hash->shouldHaveReceived('check')->once()->with('a wrong password', CheckPassword::DUMMY_HASH);
});

it('REQ-2.2 never opens a Google-only account by password', function (): void {
    passwordAccount(['password' => null, 'google_id' => 'sub-1']);

    postJson('/api/v1/auth/email/login', passwordLoginBody('any password at all'))
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.invalid_credentials']]);
});

it('REQ-2.4 locks after 5 wrong passwords, even with the right one', function (): void {
    passwordAccount();

    foreach (range(1, 5) as $attempt) {
        postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'))->assertStatus(401);
    }

    $response = postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'));

    $response->assertStatus(429)->assertJsonPath('error.code', 'auth.password_locked');
    expect($response->json('error.retry_after_seconds'))->toBeInt()->toBeGreaterThan(0)->toBeLessThanOrEqual(900);
});

it('REQ-2.5 resets the count on a success', function (): void {
    passwordAccount();

    foreach (range(1, 4) as $attempt) {
        postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'))->assertStatus(401);
    }

    postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'))->assertOk();

    foreach (range(1, 4) as $attempt) {
        postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'))->assertStatus(401);
    }

    postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'))->assertOk();
});

it('REQ-2.4 ends the lock after 15 minutes', function (): void {
    passwordAccount();

    foreach (range(1, 5) as $attempt) {
        postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'));
    }

    travel(15)->minutes();
    travel(1)->seconds();

    postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'))->assertOk();
});

it('REQ-2.5 does not extend a lock with attempts made during it', function (): void {
    $user = passwordAccount();

    foreach (range(1, 8) as $attempt) {
        postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'));
    }

    $lockedUntil = $user->fresh()?->password_locked_until;
    travel(5)->minutes();
    postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'))->assertStatus(429);

    expect($lockedUntil)->not->toBeNull();
    expect($user->fresh()?->password_locked_until?->equalTo($lockedUntil))->toBeTrue();
});

it('REQ-1.11 gives a code for each invalid field', function (): void {
    postJson('/api/v1/auth/email/login', ['email' => 'not-an-address', 'device_id' => 'nope'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'email' => ['email.email'],
            'password' => ['password.required'],
            'device_id' => ['device_id.uuid'],
        ]]]);
});

it('REQ-6.2 needs a device id from a client that is not a web origin', function (): void {
    passwordAccount();

    postJson('/api/v1/auth/email/login', ['email' => 'amira@example.com', 'password' => 'the right password'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['device_id' => ['device_id.required']]]]);
});
