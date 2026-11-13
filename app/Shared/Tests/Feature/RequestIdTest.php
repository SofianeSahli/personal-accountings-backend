<?php

declare(strict_types=1);

use App\Shared\Http\AssignRequestId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Route::middleware('api')->prefix('api/v1/_probe')->group(function (): void {
        Route::get('ok', fn () => ['data' => []]);
        Route::post('crash', fn () => throw new RuntimeException('crashed'));
        Route::get('query', fn () => throw new QueryException('mysql', 'select * from users where email = ?', ['someone@example.com'], new PDOException('SQLSTATE[42S02]')));
    });
});

function captureLogs(): TestHandler
{
    $handler = new TestHandler;
    config([
        'logging.channels.probe' => ['driver' => 'custom', 'via' => fn (): Logger => new Logger('probe', [$handler])],
        'logging.default' => 'probe',
    ]);
    Log::forgetChannel('probe');

    return $handler;
}

/**
 * @param  TestResponse<Response>  $response
 */
function requestIdOf(TestResponse $response): string
{
    return (string) $response->headers->get(AssignRequestId::HEADER);
}

it('REQ-6.6 gives every API response an X-Request-Id header', function (string $method, string $uri, int $status): void {
    $response = $method === 'GET' ? getJson($uri) : postJson($uri);

    $response->assertStatus($status)->assertHeader(AssignRequestId::HEADER);
    expect(requestIdOf($response))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/');
})->with([
    'success' => ['GET', '/api/v1/_probe/ok', 200],
    'unknown route' => ['GET', '/api/v1/_probe/nowhere', 404],
    'crash' => ['POST', '/api/v1/_probe/crash', 500],
]);

it('REQ-6.6 gives each request its own id', function (): void {
    expect(requestIdOf(getJson('/api/v1/_probe/ok')))->not->toBe(requestIdOf(getJson('/api/v1/_probe/ok')));
});

it('REQ-6.6 ignores a request id sent by the client', function (): void {
    $response = getJson('/api/v1/_probe/ok', [AssignRequestId::HEADER => 'injected-id']);

    expect(requestIdOf($response))->not->toBe('injected-id');
});

it('REQ-6.6 logs a 5xx error with the request id and without the request body', function (): void {
    $logs = captureLogs();

    $response = postJson('/api/v1/_probe/crash', ['email' => 'someone@example.com', 'amount' => 123456]);

    $records = $logs->getRecords();
    expect($records)->toHaveCount(1)
        ->and($records[0]->extra[AssignRequestId::CONTEXT_KEY] ?? null)->toBe(requestIdOf($response));

    $logged = (string) json_encode($records[0]->toArray());
    expect($logged)->not->toContain('someone@example.com');
    expect($logged)->not->toContain('123456');
});

it('REQ-6.6 logs a failed query with its SQL state only', function (): void {
    $logs = captureLogs();

    getJson('/api/v1/_probe/query')->assertStatus(500);

    $records = $logs->getRecords();
    expect($records)->toHaveCount(1)
        ->and($records[0])->toBeInstanceOf(LogRecord::class)
        ->and($records[0]->message)->toBe('database.query_failed');

    $logged = (string) json_encode($records[0]->toArray());
    expect($logged)->not->toContain('someone@example.com');
    expect($logged)->not->toContain('select');
});
