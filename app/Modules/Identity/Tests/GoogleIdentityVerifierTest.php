<?php

declare(strict_types=1);

use App\Modules\Identity\Google\GoogleIdentityVerifier;
use App\Shared\Errors\ApiException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\travel;

const GOOGLE_KEYS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
const TEST_KEY_ID = 'test-key';
const TEST_CLIENT_ID = 'expenses-test-client';

/**
 * @return array{private_pem: string, n: string, e: string}
 */
function generateRsaKeyPair(): array
{
    $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    if ($privateKey === false) {
        throw new RuntimeException('OpenSSL could not create a key.');
    }

    openssl_pkey_export($privateKey, $privatePem);
    $details = openssl_pkey_get_details($privateKey);

    return ['private_pem' => (string) $privatePem, 'n' => $details['rsa']['n'], 'e' => $details['rsa']['e']];
}

/**
 * @return array{private_pem: string, n: string, e: string}
 */
function googleTestKey(): array
{
    static $key = null;

    return $key ??= generateRsaKeyPair();
}

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function fakeGoogleKeys(): void
{
    $key = googleTestKey();

    Http::fake([GOOGLE_KEYS_URL => Http::response(
        ['keys' => [[
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => TEST_KEY_ID,
            'n' => base64UrlEncode($key['n']),
            'e' => base64UrlEncode($key['e']),
        ]]],
        200,
        ['Cache-Control' => 'public, max-age=3600'],
    )]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function googleClaims(array $overrides = []): array
{
    return array_merge([
        'iss' => 'https://accounts.google.com',
        'aud' => TEST_CLIENT_ID,
        'sub' => '1234567890',
        'email' => 'Amira@Gmail.com',
        'email_verified' => true,
        'name' => 'Amira',
        'iat' => time(),
        'exp' => time() + 3600,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function signedGoogleToken(array $overrides = []): string
{
    return JWT::encode(googleClaims($overrides), googleTestKey()['private_pem'], 'RS256', TEST_KEY_ID);
}

function expectGoogleRefusal(string $idToken, string $code, int $status): void
{
    try {
        app(GoogleIdentityVerifier::class)->verify($idToken);
    } catch (ApiException $exception) {
        expect($exception->errorCode)->toBe($code);
        expect($exception->status)->toBe($status);

        return;
    }

    throw new RuntimeException('The identity was accepted.');
}

beforeEach(function (): void {
    config(['identity.google_client_ids' => [TEST_CLIENT_ID]]);
});

it('REQ-4.1 accepts a valid identity and lowercases its email', function (): void {
    fakeGoogleKeys();

    $identity = app(GoogleIdentityVerifier::class)->verify(signedGoogleToken());

    expect($identity->sub)->toBe('1234567890');
    expect($identity->email)->toBe('amira@gmail.com');
    expect($identity->name)->toBe('Amira');
    expect($identity->isAuthoritativeForEmail())->toBeTrue();
});

it('REQ-4.1 accepts either Google issuer', function (): void {
    fakeGoogleKeys();

    $identity = app(GoogleIdentityVerifier::class)->verify(signedGoogleToken(['iss' => 'accounts.google.com']));

    expect($identity->sub)->toBe('1234567890');
});

it('REQ-4.1 accepts several client ids', function (): void {
    fakeGoogleKeys();
    config(['identity.google_client_ids' => ['android-client', 'ios-client']]);

    $identity = app(GoogleIdentityVerifier::class)->verify(signedGoogleToken(['aud' => 'ios-client']));

    expect($identity->sub)->toBe('1234567890');
});

it('REQ-4.5 keeps the hosted domain and treats it as authoritative', function (): void {
    fakeGoogleKeys();

    $identity = app(GoogleIdentityVerifier::class)->verify(signedGoogleToken([
        'email' => 'amira@family.tn',
        'hd' => 'family.tn',
    ]));

    expect($identity->isAuthoritativeForEmail())->toBeTrue();
});

it('REQ-4.6 is not authoritative for an address outside Gmail with no hosted domain', function (): void {
    fakeGoogleKeys();

    $identity = app(GoogleIdentityVerifier::class)->verify(signedGoogleToken(['email' => 'amira@family.tn']));

    expect($identity->isAuthoritativeForEmail())->toBeFalse();
});

it('REQ-4.2 refuses a token signed with another key', function (): void {
    fakeGoogleKeys();
    $forged = JWT::encode(googleClaims(), generateRsaKeyPair()['private_pem'], 'RS256', TEST_KEY_ID);

    expectGoogleRefusal($forged, 'auth.google_invalid', 401);
});

it('REQ-4.2 refuses a token that fails a check', function (array $claims): void {
    fakeGoogleKeys();

    expectGoogleRefusal(signedGoogleToken($claims), 'auth.google_invalid', 401);
})->with([
    'wrong issuer' => [['iss' => 'https://evil.example.com']],
    'another application' => [['aud' => 'another-application']],
    'expired beyond the leeway' => [['exp' => time() - 3600]],
    'unverified email' => [['email_verified' => false]],
]);

it('REQ-4.1 accepts a token expired within the 60-second leeway', function (): void {
    fakeGoogleKeys();

    $identity = app(GoogleIdentityVerifier::class)->verify(signedGoogleToken(['exp' => time() - 30]));

    expect($identity->sub)->toBe('1234567890');
});

it('REQ-4.2 refuses something that is not a token without calling Google', function (): void {
    fakeGoogleKeys();

    expectGoogleRefusal('not-a-token', 'auth.google_invalid', 401);

    Http::assertNothingSent();
});

it('REQ-4.1 caches the keys for the max-age Google sends', function (): void {
    fakeGoogleKeys();
    $verifier = app(GoogleIdentityVerifier::class);

    $verifier->verify(signedGoogleToken());
    $verifier->verify(signedGoogleToken());

    Http::assertSentCount(1);

    travel(3601)->seconds();
    $verifier->verify(signedGoogleToken());

    Http::assertSentCount(2);
});

it('REQ-4.2 answers auth.google_unavailable when the keys cannot be fetched', function (): void {
    Http::fake([GOOGLE_KEYS_URL => Http::response('', 500)]);

    expectGoogleRefusal(signedGoogleToken(), 'auth.google_unavailable', 503);
});
