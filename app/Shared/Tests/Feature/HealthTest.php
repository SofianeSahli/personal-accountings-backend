<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\json;
use function Pest\Laravel\withServerVariables;

const HEALTH_URI = '/api/v1/health';
const HEALTH_LIMIT_PER_MINUTE = 60;

beforeEach(function (): void {
    Cache::store('file')->clear();
});

afterEach(function (): void {
    config(['database.default' => 'mysql']);
    Cache::store('file')->clear();
});

function takeDatabaseDown(): void
{
    config([
        'database.connections.mysql_down' => [...config('database.connections.mysql'), 'database' => 'missing_test'],
        'database.default' => 'mysql_down',
    ]);
    DB::purge('mysql_down');
}

it('REQ-9.1 answers ok without authentication when the database is up', function (): void {
    getJson(HEALTH_URI)
        ->assertOk()
        ->assertExactJson(['data' => ['status' => 'ok']]);
});

it('REQ-9.3 reveals no version, host, configuration or timing', function (): void {
    $body = (string) getJson(HEALTH_URI)->getContent();

    expect($body)->toBe('{"data":{"status":"ok"}}');
});

it('REQ-9.2 answers 503 health.database_unavailable when the database is unreachable', function (): void {
    takeDatabaseDown();

    getJson(HEALTH_URI)
        ->assertStatus(503)
        ->assertExactJson(['error' => ['code' => 'health.database_unavailable']]);
});

it('REQ-9.4 throttles by IP and answers 429 with retry_after_seconds', function (): void {
    foreach (range(1, HEALTH_LIMIT_PER_MINUTE) as $attempt) {
        getJson(HEALTH_URI)->assertOk();
    }

    getJson(HEALTH_URI)
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('error.code', 'request.throttled')
        ->assertJsonStructure(['error' => ['code', 'retry_after_seconds']]);
});

it('REQ-9.4 keeps throttling while the database is down, so the limiter never uses it', function (): void {
    takeDatabaseDown();

    foreach (range(1, HEALTH_LIMIT_PER_MINUTE) as $attempt) {
        getJson(HEALTH_URI)->assertStatus(503);
    }

    getJson(HEALTH_URI)->assertStatus(429);
});

it('REQ-9.4 counts other IPs separately', function (): void {
    foreach (range(1, HEALTH_LIMIT_PER_MINUTE) as $attempt) {
        getJson(HEALTH_URI)->assertOk();
    }

    withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);

    getJson(HEALTH_URI)->assertOk();
});

it('REQ-9.5 answers 405 to any method but GET', function (string $method): void {
    json($method, HEALTH_URI)
        ->assertStatus(405)
        ->assertExactJson(['error' => ['code' => 'request.method_not_allowed']]);
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('REQ-9.6 no longer serves Laravel\'s /up route', function (): void {
    get('/up')->assertNotFound();
});

it('REQ-12.3 answers 404 request.not_found on former legacy paths', function (string $method, string $uri): void {
    json($method, $uri)
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'request.not_found']]);
})->with([
    ['GET', '/api/v1/categories'],
    ['GET', '/api/v1/profile'],
    ['POST', '/api/v1/money-transition'],
    ['GET', '/api/v1/money-transition/summary'],
]);
