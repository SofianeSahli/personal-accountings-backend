<?php

declare(strict_types=1);

use App\Modules\Identity\Tests\Support\IdentityScenario;

use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

function googleTestSignIn(string $deviceId, ?string $termsVersion = null): string
{
    return (string) postJson('/api/v1/auth/google', array_filter([
        'id_token' => 'test:amira@gmail.com',
        'device_id' => $deviceId,
        'terms_version' => $termsVersion,
    ]))
        ->assertOk()
        ->json('data.token');
}

beforeEach(function (): void {
    config(['identity.auth_test_mode' => true]);

    googleTestSignIn(IdentityScenario::deviceId(), (string) config('identity.terms_version'));
});

it('REQ-6.2 gives a token to a new device without asking for the terms again', function (): void {
    IdentityScenario::assertTokenWorks(googleTestSignIn(IdentityScenario::deviceId()));
});

it('REQ-6.3 replaces the token of a device that signs in again', function (): void {
    $deviceId = IdentityScenario::deviceId();

    $firstToken = googleTestSignIn($deviceId);
    $secondToken = googleTestSignIn($deviceId);

    IdentityScenario::assertTokenRefused($firstToken);
    IdentityScenario::assertTokenWorks($secondToken);
});

it('REQ-6.4 refuses a token 90 days after it was issued', function (): void {
    $token = googleTestSignIn(IdentityScenario::deviceId());

    travel(129599)->minutes();
    IdentityScenario::assertTokenWorks($token);

    travel(2)->minutes();
    IdentityScenario::assertTokenRefused($token);
});

it('REQ-7.2 signs out of the current device only', function (): void {
    $phoneToken = googleTestSignIn(IdentityScenario::deviceId());
    $tabletToken = googleTestSignIn(IdentityScenario::deviceId());

    postJson('/api/v1/auth/logout', [], IdentityScenario::bearer($phoneToken))->assertNoContent();

    IdentityScenario::assertTokenRefused($phoneToken);
    IdentityScenario::assertTokenWorks($tabletToken);
});

it('REQ-6.7 refuses to sign out with no session', function (): void {
    postJson('/api/v1/auth/logout')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('REQ-8.1 limits sign-in attempts per network origin', function (): void {
    foreach (range(1, 28) as $attempt) {
        postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_id' => IdentityScenario::deviceId()]);
    }

    postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_id' => IdentityScenario::deviceId()])
        ->assertStatus(401);

    $response = postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_id' => IdentityScenario::deviceId()]);

    $response->assertStatus(429)->assertJsonPath('error.code', 'request.throttled');
    expect($response->json('error.retry_after_seconds'))->toBeGreaterThan(0);
});
