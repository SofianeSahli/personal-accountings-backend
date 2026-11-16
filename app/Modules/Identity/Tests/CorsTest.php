<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\call;
use function Pest\Laravel\getJson;

const ALLOWED_ORIGIN = 'https://app.expenses.test';

beforeEach(function (): void {
    config(['cors.allowed_origins' => [ALLOWED_ORIGIN]]);
});

/**
 * @return TestResponse<Response>
 */
function preflight(string $uri, string $origin): TestResponse
{
    return call('OPTIONS', $uri, [], [], [], [
        'HTTP_ORIGIN' => $origin,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-xsrf-token',
    ]);
}

it('REQ-6.6 allows a configured origin with credentials', function (string $uri): void {
    preflight($uri, ALLOWED_ORIGIN)
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', ALLOWED_ORIGIN)
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
})->with([
    'api' => ['/api/v1/auth/email/login'],
    'csrf cookie' => ['/sanctum/csrf-cookie'],
]);

it('REQ-6.6 gives no CORS grant to any other origin', function (): void {
    $grantedOrigin = preflight('/api/v1/auth/email/login', 'https://evil.example.com')
        ->headers->get('Access-Control-Allow-Origin');

    expect($grantedOrigin)->not->toBe('https://evil.example.com');
    expect($grantedOrigin)->not->toBe('*');
});

it('REQ-6.6 lets the browser read the request id', function (): void {
    getJson('/api/v1/health', ['Origin' => ALLOWED_ORIGIN])
        ->assertHeader('Access-Control-Allow-Origin', ALLOWED_ORIGIN)
        ->assertHeader('Access-Control-Expose-Headers', 'X-Request-Id');
});
