<?php

declare(strict_types=1);

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCode;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\Locale;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registerBody(array $overrides = []): array
{
    return array_merge([
        'name' => 'Amira',
        'email' => 'amira@example.com',
        'password' => 'long enough secret',
        'terms_version' => config('identity.terms_version'),
    ], $overrides);
}

/**
 * @return array<string, string>
 */
function confirmBody(string $code, string $email = 'amira@example.com'): array
{
    return ['email' => $email, 'code' => $code, 'device_id' => IdentityScenario::deviceId()];
}

function soleUser(): User
{
    return User::query()->sole();
}

beforeEach(function (): void {
    Mail::fake();
});

it('REQ-1.1 registers with a queued code and no session', function (): void {
    $response = postJson('/api/v1/auth/email/register', registerBody(['phone' => '+216 20 123 456']));

    $response->assertStatus(202);
    expect($response->getContent())->toBe('');

    $user = soleUser();
    expect($user->hasConfirmedEmail())->toBeFalse();
    expect($user->phone)->toBe('+21620123456');
    expect($user->locale)->toBe(Locale::Fr);
    expect($user->terms_version)->toBe(config('identity.terms_version'));
    expect($user->terms_accepted_at)->not->toBeNull();
    expect($user->tokens()->count())->toBe(0);

    Mail::assertQueued(EmailCodeMail::class, fn (EmailCodeMail $mail): bool => $mail->hasTo('amira@example.com')
        && $mail->purpose === EmailCodePurpose::EmailConfirmation);
});

it('REQ-9.1 saves the chosen locale and mails the code in it', function (): void {
    postJson('/api/v1/auth/email/register', registerBody(['locale' => 'en']))->assertStatus(202);

    expect(soleUser()->locale)->toBe(Locale::En);
    Mail::assertQueued(EmailCodeMail::class, fn (EmailCodeMail $mail): bool => $mail->locale === 'en');
});

it('REQ-9.1 refuses a locale that is not supported', function (): void {
    postJson('/api/v1/auth/email/register', registerBody(['locale' => 'de']))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['locale' => ['locale.enum']]]]);
});

it('REQ-1.2 stores only a hash of the code', function (): void {
    postJson('/api/v1/auth/email/register', registerBody())->assertStatus(202);

    $emailCode = EmailCode::query()->sole();
    expect($emailCode->code_hash)->not->toBe(IdentityScenario::latestMailedCode());
    expect(Hash::check(IdentityScenario::latestMailedCode(), $emailCode->code_hash))->toBeTrue();
});

it('REQ-1.3 replaces the name, password, locale and code of an unconfirmed sign-up', function (): void {
    postJson('/api/v1/auth/email/register', registerBody())->assertStatus(202);
    $firstHash = EmailCode::query()->sole()->code_hash;

    postJson('/api/v1/auth/email/register', registerBody([
        'name' => 'Real owner',
        'password' => 'the real password',
        'locale' => 'en',
    ]))->assertStatus(202);

    $user = soleUser();
    expect($user->name)->toBe('Real owner');
    expect($user->locale)->toBe(Locale::En);
    expect(Hash::check('the real password', (string) $user->password))->toBeTrue();
    expect(EmailCode::query()->sole()->code_hash)->not->toBe($firstHash);
});

it('REQ-1.4 refuses an address with a confirmed account and leaves it unchanged', function (): void {
    $user = User::factory()->create(['email' => 'amira@example.com', 'name' => 'Amira']);

    postJson('/api/v1/auth/email/register', registerBody(['name' => 'Stranger']))
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'email.taken']]);

    expect($user->fresh()?->name)->toBe('Amira');
    Mail::assertNothingQueued();
});

it('REQ-1.5 confirms the address and gives a session', function (): void {
    postJson('/api/v1/auth/email/register', registerBody());

    $response = postJson('/api/v1/auth/email/confirm', confirmBody(IdentityScenario::latestMailedCode()));

    $response->assertOk()
        ->assertJsonPath('data.status', 'signed_in')
        ->assertJsonPath('data.account.email', 'amira@example.com');
    expect(soleUser()->hasConfirmedEmail())->toBeTrue();
    getJson('/api/v1/me', IdentityScenario::bearer((string) $response->json('data.token')))->assertOk();
});

it('REQ-10.1 accepts 000000 in test mode', function (): void {
    config(['identity.auth_test_mode' => true]);
    postJson('/api/v1/auth/email/register', registerBody());

    postJson('/api/v1/auth/email/confirm', confirmBody('000000'))->assertOk();
});

it('REQ-1.6 refuses a wrong code', function (): void {
    postJson('/api/v1/auth/email/register', registerBody());

    postJson('/api/v1/auth/email/confirm', confirmBody(IdentityScenario::codeOtherThan(IdentityScenario::latestMailedCode())))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    expect(soleUser()->hasConfirmedEmail())->toBeFalse();
});

it('REQ-1.2 refuses a code after 15 minutes', function (): void {
    postJson('/api/v1/auth/email/register', registerBody());

    travel(15)->minutes();
    travel(1)->seconds();

    postJson('/api/v1/auth/email/confirm', confirmBody(IdentityScenario::latestMailedCode()))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('REQ-1.2 refuses a used code', function (): void {
    postJson('/api/v1/auth/email/register', registerBody());
    $code = IdentityScenario::latestMailedCode();

    postJson('/api/v1/auth/email/confirm', confirmBody($code))->assertOk();

    postJson('/api/v1/auth/email/confirm', confirmBody($code))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('REQ-1.6 refuses an unknown address the same way', function (): void {
    postJson('/api/v1/auth/email/confirm', confirmBody('123456', 'nobody@example.com'))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('REQ-1.7 kills a code after 5 wrong tries', function (): void {
    postJson('/api/v1/auth/email/register', registerBody());
    $rightCode = IdentityScenario::latestMailedCode();

    foreach (range(1, 5) as $attempt) {
        postJson('/api/v1/auth/email/confirm', confirmBody(IdentityScenario::codeOtherThan($rightCode)))->assertStatus(422);
    }

    postJson('/api/v1/auth/email/confirm', confirmBody($rightCode))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('REQ-1.8 resends a new code for an unconfirmed sign-up', function (): void {
    postJson('/api/v1/auth/email/register', registerBody());
    $firstHash = EmailCode::query()->sole()->code_hash;

    $response = postJson('/api/v1/auth/email/resend', ['email' => 'amira@example.com']);

    $response->assertStatus(202);
    expect($response->getContent())->toBe('');
    expect(EmailCode::query()->sole()->code_hash)->not->toBe($firstHash);
    Mail::assertQueued(EmailCodeMail::class, 2);
});

it('REQ-1.8 gives the same answer to a resend for any address and sends nothing', function (Closure $prepare, string $email): void {
    $prepare();

    $response = postJson('/api/v1/auth/email/resend', ['email' => $email]);

    $response->assertStatus(202);
    expect($response->getContent())->toBe('');
    Mail::assertNothingQueued();
})->with([
    'unknown' => [fn (): null => null, 'nobody@example.com'],
    'confirmed' => [fn (): User => User::factory()->create(['email' => 'amira@example.com']), 'amira@example.com'],
]);

it('REQ-1.1 compares addresses in lowercase', function (): void {
    postJson('/api/v1/auth/email/register', registerBody(['email' => 'Amira@Example.COM']))->assertStatus(202);

    expect(soleUser()->email)->toBe('amira@example.com');

    postJson('/api/v1/auth/email/confirm', confirmBody(IdentityScenario::latestMailedCode(), 'AMIRA@example.com'))->assertOk();

    postJson('/api/v1/auth/email/register', registerBody(['email' => 'AMIRA@EXAMPLE.COM']))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'email.taken');
});

it('REQ-1.1 refuses a password under 8 characters', function (): void {
    postJson('/api/v1/auth/email/register', registerBody(['password' => 'short']))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['password' => ['password.min']]]]);
});

it('REQ-1.1 accepts 72 bytes and refuses more', function (): void {
    postJson('/api/v1/auth/email/register', registerBody(['password' => str_repeat('a', 72)]))->assertStatus(202);

    postJson('/api/v1/auth/email/register', registerBody([
        'email' => 'other@example.com',
        'password' => str_repeat('é', 37),
    ]))->assertStatus(422)->assertJsonPath('error.fields.password', ['password.bcrypt_limit']);
});

it('REQ-1.11 gives a code for each invalid field', function (): void {
    postJson('/api/v1/auth/email/register', [
        'name' => '   ',
        'email' => 'not-an-address',
        'password' => 'long enough secret',
        'terms_version' => '2020-01',
        'phone' => '20123456',
    ])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'name' => ['name.required'],
            'email' => ['email.email'],
            'terms_version' => ['terms_version.in'],
            'phone' => ['phone.international_phone'],
        ]]]);

    expect(User::query()->count())->toBe(0);
});

it('REQ-1.9 needs the required fields, the terms included', function (): void {
    postJson('/api/v1/auth/email/register', [])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'name' => ['name.required'],
            'email' => ['email.required'],
            'password' => ['password.required'],
            'terms_version' => ['terms_version.required'],
        ]]]);

    postJson('/api/v1/auth/email/confirm', ['email' => 'amira@example.com', 'code' => '12ab', 'device_id' => 'nope'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'code' => ['code.digits'],
            'device_id' => ['device_id.uuid'],
        ]]]);
});

it('REQ-1.11 needs an address to resend a code', function (): void {
    postJson('/api/v1/auth/email/resend', ['email' => 'not-an-address'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['email' => ['email.email']]]]);
});

it('REQ-1.11 answers fields of the wrong type with codes, never with text', function (): void {
    $response = postJson('/api/v1/auth/email/register', [
        'name' => ['a'],
        'email' => ['b'],
        'password' => ['c'],
        'terms_version' => ['d'],
        'phone' => ['e'],
        'locale' => ['f'],
    ]);

    $response->assertStatus(422);

    $fields = $response->json('error.fields');
    expect($fields)->toBeArray()->toHaveKeys(['name', 'email', 'password', 'terms_version', 'phone', 'locale']);

    foreach ((array) $fields as $field => $codes) {
        foreach ((array) $codes as $code) {
            expect($code)->toStartWith("{$field}.");
            expect($code)->not->toContain(' ');
        }
    }
});

it('REQ-8.2 limits email sending per address, across sign-up and resend', function (): void {
    postJson('/api/v1/auth/email/register', registerBody())->assertStatus(202);
    postJson('/api/v1/auth/email/resend', ['email' => 'amira@example.com'])->assertStatus(202);
    postJson('/api/v1/auth/email/resend', ['email' => 'AMIRA@example.com'])->assertStatus(202);

    postJson('/api/v1/auth/email/resend', ['email' => 'amira@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');

    postJson('/api/v1/auth/email/resend', ['email' => 'other@example.com'])->assertStatus(202);
});

it('REQ-8.2 limits email sending per network origin', function (): void {
    foreach (range(1, 10) as $attempt) {
        postJson('/api/v1/auth/email/resend', ['email' => "person{$attempt}@example.com"])->assertStatus(202);
    }

    postJson('/api/v1/auth/email/resend', ['email' => 'person11@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});
