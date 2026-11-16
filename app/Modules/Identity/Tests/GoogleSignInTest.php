<?php

declare(strict_types=1);

use App\Modules\Identity\Google\GoogleIdentity;
use App\Modules\Identity\Google\GoogleIdentityChecker;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\freezeSecond;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function fakeGoogleIdentity(GoogleIdentity $identity): void
{
    app()->instance(GoogleIdentityChecker::class, new readonly class($identity) implements GoogleIdentityChecker
    {
        public function __construct(private GoogleIdentity $identity) {}

        public function verify(string $idToken): GoogleIdentity
        {
            return $this->identity;
        }
    });
}

function fakeGoogleFailure(string $code, int $status): void
{
    app()->instance(GoogleIdentityChecker::class, new readonly class($code, $status) implements GoogleIdentityChecker
    {
        public function __construct(private string $code, private int $status) {}

        public function verify(string $idToken): GoogleIdentity
        {
            throw new ApiException($this->code, $this->status);
        }
    });
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function googleSignInBody(array $overrides = []): array
{
    return array_merge([
        'id_token' => 'signed-by-google',
        'device_id' => IdentityScenario::deviceId(),
        'terms_version' => config('identity.terms_version'),
    ], $overrides);
}

it('REQ-4.4 creates a confirmed account with the terms at a first sign-in', function (): void {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));
    freezeSecond();

    $response = postJson('/api/v1/auth/google', googleSignInBody());

    $response->assertOk()
        ->assertJsonPath('data.status', 'signed_in')
        ->assertJsonPath('data.account.name', 'Amira')
        ->assertJsonPath('data.account.email', 'amira@gmail.com')
        ->assertJsonPath('data.account.phone', null)
        ->assertJsonPath('data.account.locale', 'fr');

    $user = User::query()->where('google_id', 'sub-1')->firstOrFail();
    expect($user->terms_version)->toBe(config('identity.terms_version'));
    expect($user->terms_accepted_at->equalTo(now()))->toBeTrue();
    expect($user->hasConfirmedEmail())->toBeTrue();
    expect($user->password)->toBeNull();

    getJson('/api/v1/me', IdentityScenario::bearer((string) $response->json('data.token')))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('REQ-9.1 saves the locale chosen at a first sign-in', function (): void {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    postJson('/api/v1/auth/google', googleSignInBody(['locale' => 'en']))
        ->assertOk()
        ->assertJsonPath('data.account.locale', 'en');
});

it('REQ-1.10 saves the phone in its standard form', function (): void {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    postJson('/api/v1/auth/google', googleSignInBody(['phone' => '00216 20 123 456']))
        ->assertOk()
        ->assertJsonPath('data.account.phone', '+21620123456');
});

it('REQ-4.4 needs the terms at a first sign-in', function (): void {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    postJson('/api/v1/auth/google', googleSignInBody(['terms_version' => null]))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'auth.terms_required']]);

    expect(User::query()->count())->toBe(0);
});

it('REQ-1.9 refuses an outdated terms version', function (): void {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    postJson('/api/v1/auth/google', googleSignInBody(['terms_version' => '2020-01']))
        ->assertStatus(422)
        ->assertExactJson(['error' => [
            'code' => 'validation.failed',
            'fields' => ['terms_version' => ['terms_version.in']],
        ]]);
});

it('REQ-1.11 gives a code for each invalid field and creates no account', function (): void {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    postJson('/api/v1/auth/google', googleSignInBody(['phone' => '20123456', 'device_id' => 'not-a-uuid', 'locale' => 'de']))
        ->assertStatus(422)
        ->assertExactJson(['error' => [
            'code' => 'validation.failed',
            'fields' => [
                'device_id' => ['device_id.uuid'],
                'phone' => ['phone.international_phone'],
                'locale' => ['locale.enum'],
            ],
        ]]);

    expect(User::query()->count())->toBe(0);
});

it('REQ-6.2 needs an identity and a device', function (): void {
    postJson('/api/v1/auth/google', [])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.id_token', ['id_token.required'])
        ->assertJsonPath('error.fields.device_id', ['device_id.required']);
});

it('REQ-4.2 answers a refused identity with auth.google_invalid', function (): void {
    fakeGoogleFailure('auth.google_invalid', 401);

    postJson('/api/v1/auth/google', googleSignInBody())
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.google_invalid']]);

    expect(User::query()->count())->toBe(0);
});

it('REQ-4.2 answers unreachable keys with auth.google_unavailable', function (): void {
    fakeGoogleFailure('auth.google_unavailable', 503);

    postJson('/api/v1/auth/google', googleSignInBody())
        ->assertStatus(503)
        ->assertExactJson(['error' => ['code' => 'auth.google_unavailable']]);
});

it('REQ-4.6 refuses a match on an address Google is not authoritative for', function (Closure $existingAccount): void {
    $existing = $existingAccount();
    expect($existing)->toBeInstanceOf(User::class);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', $existing->email, 'Stranger'));

    postJson('/api/v1/auth/google', googleSignInBody())
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'auth.google_email_conflict']]);

    expect($existing->fresh())->not->toBeNull();
    expect($existing->fresh()?->google_id)->toBeNull();
})->with([
    'confirmed' => [fn (): User => User::factory()->create(['email' => 'amira@family.tn'])],
    'unconfirmed' => [fn (): User => User::factory()->unverified()->create(['email' => 'amira@family.tn'])],
]);

it('REQ-4.6 refuses an account already linked to another Google identity', function (): void {
    $user = User::factory()->create(['email' => 'amira@gmail.com', 'google_id' => 'sub-other']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    postJson('/api/v1/auth/google', googleSignInBody())
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'auth.google_email_conflict']]);

    expect($user->fresh()?->google_id)->toBe('sub-other');
});

it('REQ-4.5 links Google to a confirmed account on a Gmail address', function (): void {
    $user = User::factory()->create(['email' => 'amira@gmail.com']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    postJson('/api/v1/auth/google', googleSignInBody(['terms_version' => null]))
        ->assertOk()
        ->assertJsonPath('data.account.id', $user->id);

    expect($user->fresh()?->google_id)->toBe('sub-1');
    expect(User::query()->count())->toBe(1);
});

it('REQ-4.5 links Google to a confirmed account on a hosted domain', function (): void {
    $user = User::factory()->create(['email' => 'amira@family.tn']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@family.tn', 'Amira', 'family.tn'));

    postJson('/api/v1/auth/google', googleSignInBody())
        ->assertOk()
        ->assertJsonPath('data.account.id', $user->id);
});

it('REQ-4.5 replaces an unconfirmed sign-up when Google is authoritative', function (): void {
    $stranger = User::factory()->unverified()->create(['email' => 'amira@gmail.com', 'name' => 'Stranger']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    postJson('/api/v1/auth/google', googleSignInBody())
        ->assertOk()
        ->assertJsonPath('data.account.name', 'Amira');

    expect($stranger->fresh())->toBeNull();
    expect(User::query()->sole()->password)->toBeNull();
});

it('REQ-4.4 keeps an unconfirmed sign-up when the terms are still missing', function (): void {
    $stranger = User::factory()->unverified()->create(['email' => 'amira@gmail.com']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    postJson('/api/v1/auth/google', googleSignInBody(['terms_version' => null]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'auth.terms_required');

    expect($stranger->fresh())->not->toBeNull();
});

it('REQ-4.3 refreshes the saved name and never changes the saved email', function (): void {
    $user = User::factory()->create(['email' => 'amira@gmail.com', 'google_id' => 'sub-1', 'name' => 'Old name']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira.new@gmail.com', 'New name'));

    postJson('/api/v1/auth/google', googleSignInBody(['terms_version' => null]))
        ->assertOk()
        ->assertJsonPath('data.account.name', 'New name')
        ->assertJsonPath('data.account.email', 'amira@gmail.com');

    expect($user->fresh()?->email)->toBe('amira@gmail.com');
});

it('REQ-4.3 keeps the saved name when Google sends none', function (): void {
    User::factory()->create(['email' => 'amira@gmail.com', 'google_id' => 'sub-1', 'name' => 'Amira']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', null));

    postJson('/api/v1/auth/google', googleSignInBody())
        ->assertOk()
        ->assertJsonPath('data.account.name', 'Amira');
});

it('REQ-4.7 ends two concurrent first sign-ins on one account', function (): void {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));
    $raceHasHappened = false;
    User::creating(function () use (&$raceHasHappened): void {
        if ($raceHasHappened) {
            return;
        }

        $raceHasHappened = true;
        DB::table('users')->insert(User::factory()->raw([
            'id' => (string) Str::orderedUuid(),
            'email' => 'amira@gmail.com',
            'google_id' => 'sub-1',
            'password' => null,
        ]));
    });

    $response = postJson('/api/v1/auth/google', googleSignInBody());

    $response->assertOk()->assertJsonPath('data.status', 'signed_in');
    expect($raceHasHappened)->toBeTrue();
    expect(User::query()->count())->toBe(1);
    expect($response->json('data.account.id'))->toBe(User::query()->sole()->id);
});

it('REQ-10.1 accepts the test identity only in test mode', function (): void {
    config(['identity.auth_test_mode' => true]);

    postJson('/api/v1/auth/google', googleSignInBody(['id_token' => 'test:amira@example.com']))
        ->assertOk()
        ->assertJsonPath('data.account.email', 'amira@example.com');
});

it('REQ-10.2 refuses the test identity with test mode off', function (): void {
    postJson('/api/v1/auth/google', googleSignInBody(['id_token' => 'test:amira@example.com']))
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.google_invalid']]);
});

it('REQ-10.2 refuses the test identity in production, even if configured', function (): void {
    config(['identity.auth_test_mode' => true]);
    app()->detectEnvironment(fn (): string => 'production');

    postJson('/api/v1/auth/google', googleSignInBody(['id_token' => 'test:amira@example.com']))
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.google_invalid']]);

    expect(User::query()->count())->toBe(0);
});
