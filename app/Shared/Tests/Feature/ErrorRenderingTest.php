<?php

declare(strict_types=1);

use App\Shared\Errors\ApiException;
use App\Shared\Tests\Support\RejectsEverything;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\json;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Route::middleware('api')->prefix('api/v1/_probe')->group(function (): void {
        Route::get('api-exception', fn () => throw new ApiException('transaction.not_found', 404));
        Route::post('validation', fn (Request $request) => $request->validate([
            'amount' => ['required', 'integer'],
            'items.*.amount' => ['required'],
            'phone' => [new RejectsEverything],
        ]));
        Route::get('unauthenticated', fn () => throw new AuthenticationException);
        Route::get('forbidden', fn () => throw new AuthorizationException);
        Route::get('missing-model', fn () => throw new ModelNotFoundException);
        Route::get('get-only', fn () => ['data' => []]);
        Route::get('throttled', fn () => throw new ThrottleRequestsException(headers: ['Retry-After' => 42]));
        Route::get('maintenance', fn () => throw new HttpException(503));
        Route::get('bad-request', fn () => throw new HttpException(400));
        Route::get('crash', fn () => throw new RuntimeException('secret SELECT * FROM users in /var/www/app/Secret.php'));
    });
});

it('REQ-6.2 renders an ApiException with its own code and status', function (): void {
    getJson('/api/v1/_probe/api-exception')
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'transaction.not_found']]);
});

it('REQ-6.3 renders validation errors as field codes without messages', function (): void {
    $response = postJson('/api/v1/_probe/validation', ['items' => [['amount' => null]], 'phone' => '1']);

    $response->assertStatus(422)->assertExactJson(['error' => [
        'code' => 'validation.failed',
        'fields' => [
            'amount' => ['amount.required'],
            'items.0.amount' => ['items.amount.required'],
            'phone' => ['phone.rejects_everything'],
        ],
    ]]);
    expect($response->getContent())->not->toContain('translated message');
});

it('REQ-6.4 maps framework exceptions to stable codes', function (string $method, string $uri, int $status, string $code): void {
    json($method, $uri)
        ->assertStatus($status)
        ->assertExactJson(['error' => ['code' => $code]]);
})->with([
    'unauthenticated' => ['GET', '/api/v1/_probe/unauthenticated', 401, 'auth.unauthenticated'],
    'forbidden' => ['GET', '/api/v1/_probe/forbidden', 403, 'auth.forbidden'],
    'missing model' => ['GET', '/api/v1/_probe/missing-model', 404, 'request.not_found'],
    'unknown route' => ['GET', '/api/v1/_probe/nowhere', 404, 'request.not_found'],
    'method not allowed' => ['POST', '/api/v1/_probe/get-only', 405, 'request.method_not_allowed'],
    'maintenance' => ['GET', '/api/v1/_probe/maintenance', 503, 'server.unavailable'],
    'other http status' => ['GET', '/api/v1/_probe/bad-request', 500, 'server.error'],
]);

it('REQ-6.4 adds retry_after_seconds and keeps the Retry-After header when throttled', function (): void {
    getJson('/api/v1/_probe/throttled')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '42')
        ->assertExactJson(['error' => ['code' => 'request.throttled', 'retry_after_seconds' => 42]]);
});

it('REQ-6.5 hides every detail of an unexpected error when debug is off', function (): void {
    config(['app.debug' => false]);

    $response = getJson('/api/v1/_probe/crash');

    $response->assertStatus(500)->assertExactJson(['error' => ['code' => 'server.error']]);
    $body = (string) $response->getContent();
    expect($body)->not->toContain('secret');
    expect($body)->not->toContain('RuntimeException');
    expect($body)->not->toContain('/var/www');
});

it('REQ-6.5 adds debug details to an unexpected error when debug is on', function (): void {
    config(['app.debug' => true]);

    getJson('/api/v1/_probe/crash')
        ->assertStatus(500)
        ->assertJsonPath('error.code', 'server.error')
        ->assertJsonPath('error.debug.exception', RuntimeException::class)
        ->assertJsonPath('error.debug.message', 'secret SELECT * FROM users in /var/www/app/Secret.php')
        ->assertJsonStructure(['error' => ['debug' => ['file', 'line', 'trace']]]);
});

it('REQ-6.5 never adds debug details to an expected error', function (): void {
    config(['app.debug' => true]);

    getJson('/api/v1/_probe/api-exception')->assertExactJson(['error' => ['code' => 'transaction.not_found']]);
});

it('REQ-7.4 answers /api errors in JSON whatever the Accept header', function (): void {
    get('/api/v1/_probe/missing-model', ['Accept' => 'text/html'])
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['error' => ['code' => 'request.not_found']]);
});

it('REQ-6.8 keeps the default rendering outside /api', function (): void {
    $response = get('/not-an-api-path', ['Accept' => 'text/html']);

    $response->assertStatus(404);
    expect($response->getContent())->not->toContain('request.not_found');
});
