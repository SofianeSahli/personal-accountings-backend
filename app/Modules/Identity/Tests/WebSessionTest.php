<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;
use App\Modules\Identity\Tests\Support\WebBrowser;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Cookie;

use function Pest\Laravel\postJson;

const WEB_PASSWORD = 'the right password';

beforeEach(function (): void {
    Mail::fake();
});

function webAccount(bool $twoFactor = false): User
{
    $factory = User::factory();

    return ($twoFactor ? $factory->withTwoFactor() : $factory)->create([
        'email' => 'amira@gmail.com',
        'password' => WEB_PASSWORD,
    ]);
}

function signedInBrowser(): WebBrowser
{
    $browser = WebBrowser::open();
    $browser->postJson('/api/v1/auth/email/login', ['email' => 'amira@gmail.com', 'password' => WEB_PASSWORD])
        ->assertOk()
        ->assertJsonPath('data.status', 'signed_in');

    return $browser;
}

it('REQ-6.1 starts a web session for a password sign-in, with no token and no device', function (): void {
    $user = webAccount();
    $browser = WebBrowser::open();

    $response = $browser->postJson('/api/v1/auth/email/login', ['email' => 'amira@gmail.com', 'password' => WEB_PASSWORD]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'signed_in')
        ->assertJsonPath('data.account.id', $user->id)
        ->assertJsonMissingPath('data.token');
    expect($user->tokens()->count())->toBe(0);
    $browser->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
});

it('REQ-6.1 regenerates the session id at sign-in', function (): void {
    webAccount();
    $browser = WebBrowser::open();
    $anonymousSessionId = $browser->sessionId();

    $browser->postJson('/api/v1/auth/email/login', ['email' => 'amira@gmail.com', 'password' => WEB_PASSWORD])->assertOk();

    expect($browser->sessionId())->not->toBe($anonymousSessionId);
});

it('REQ-6.1 sends a Secure, HttpOnly, SameSite=Lax session cookie', function (): void {
    webAccount();
    $browser = WebBrowser::open();

    $response = $browser->postJson('/api/v1/auth/email/login', ['email' => 'amira@gmail.com', 'password' => WEB_PASSWORD]);

    $cookie = $response->getCookie((string) config('session.cookie'), false);
    expect($cookie)->toBeInstanceOf(Cookie::class);
    expect($cookie?->isSecure())->toBeTrue();
    expect($cookie?->isHttpOnly())->toBeTrue();
    expect($cookie?->getSameSite())->toBe(Cookie::SAMESITE_LAX);
});

it('REQ-6.1 starts a web session when an email sign-up is confirmed', function (): void {
    config(['identity.auth_test_mode' => true]);
    $browser = WebBrowser::open();
    $browser->postJson('/api/v1/auth/email/register', [
        'name' => 'Amira',
        'email' => 'amira@gmail.com',
        'password' => WEB_PASSWORD,
        'terms_version' => config('identity.terms_version'),
    ])->assertStatus(202);

    $browser->postJson('/api/v1/auth/email/confirm', ['email' => 'amira@gmail.com', 'code' => '000000'])
        ->assertOk()
        ->assertJsonMissingPath('data.token');

    $browser->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'amira@gmail.com');
});

it('REQ-6.1 starts a web session for a Google sign-in', function (): void {
    config(['identity.auth_test_mode' => true]);
    $browser = WebBrowser::open();

    $browser->postJson('/api/v1/auth/google', [
        'id_token' => 'test:amira@gmail.com',
        'terms_version' => config('identity.terms_version'),
    ])->assertOk()->assertJsonMissingPath('data.token');

    $browser->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'amira@gmail.com');
});

it('REQ-5.3 finishes a web two-step sign-in with a web session', function (): void {
    webAccount(twoFactor: true);
    $browser = WebBrowser::open();
    $pendingSignIn = $browser->postJson('/api/v1/auth/email/login', ['email' => 'amira@gmail.com', 'password' => WEB_PASSWORD])
        ->assertJsonPath('data.status', 'code_required')
        ->json('data.pending_sign_in');

    $browser->postJson('/api/v1/auth/two-factor', ['pending_sign_in' => $pendingSignIn, 'code' => IdentityScenario::latestMailedCode()])
        ->assertOk()
        ->assertJsonPath('data.status', 'signed_in')
        ->assertJsonMissingPath('data.token');

    $browser->getJson('/api/v1/me')->assertOk();
});

it('REQ-5.4 refuses a web-started two-step sign-in finished by a token client', function (): void {
    $user = webAccount(twoFactor: true);
    $pendingSignIn = WebBrowser::open()
        ->postJson('/api/v1/auth/email/login', ['email' => 'amira@gmail.com', 'password' => WEB_PASSWORD])
        ->json('data.pending_sign_in');

    postJson('/api/v1/auth/two-factor', ['pending_sign_in' => $pendingSignIn, 'code' => IdentityScenario::latestMailedCode()])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.pending_invalid']]);

    expect($user->tokens()->count())->toBe(0);
});

it('REQ-5.4 refuses a device-started two-step sign-in finished by a web client', function (): void {
    webAccount(twoFactor: true);
    $pendingSignIn = postJson('/api/v1/auth/email/login', [
        'email' => 'amira@gmail.com',
        'password' => WEB_PASSWORD,
        'device_id' => IdentityScenario::deviceId(),
    ])->json('data.pending_sign_in');
    $browser = WebBrowser::open();

    $browser->postJson('/api/v1/auth/two-factor', ['pending_sign_in' => $pendingSignIn, 'code' => IdentityScenario::latestMailedCode()])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.pending_invalid']]);

    $browser->getJson('/api/v1/me')->assertStatus(401);
});

it('REQ-6.5 refuses a web request with no CSRF token', function (): void {
    webAccount();
    $browser = WebBrowser::open(sendsCsrfToken: false);

    $browser->postJson('/api/v1/auth/email/login', ['email' => 'amira@gmail.com', 'password' => WEB_PASSWORD])
        ->assertStatus(419)
        ->assertExactJson(['error' => ['code' => 'auth.csrf_mismatch']]);
});

it('REQ-6.7 accepts a web session on authenticated endpoints and refuses a browser without one', function (): void {
    $user = webAccount();

    WebBrowser::open()->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);

    signedInBrowser()->postJson('/api/v1/me/two-factor')->assertNoContent();
    expect($user->fresh()?->hasTwoFactor())->toBeTrue();
});

it('REQ-7.2 signs a browser out and leaves other browsers and devices signed in', function (): void {
    $user = webAccount();
    $token = IdentityScenario::tokenFor($user);
    $laptop = signedInBrowser();
    $desktop = signedInBrowser();

    $laptop->postJson('/api/v1/auth/logout')->assertNoContent();

    $laptop->getJson('/api/v1/me')->assertStatus(401);
    $desktop->getJson('/api/v1/me')->assertOk();
    IdentityScenario::assertTokenWorks($token);
});

it('REQ-3.2 ends every web session of the account at a password reset', function (): void {
    webAccount();
    $browser = signedInBrowser();
    postJson('/api/v1/auth/password/forgot', ['email' => 'amira@gmail.com'])->assertStatus(202);

    postJson('/api/v1/auth/password/reset', [
        'email' => 'amira@gmail.com',
        'code' => IdentityScenario::latestMailedCode(),
        'password' => 'the new password',
    ])->assertNoContent();

    $browser->getJson('/api/v1/me')->assertStatus(401);
});
