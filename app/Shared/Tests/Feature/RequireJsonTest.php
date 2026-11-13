<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\call;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

const ECHO_URI = '/api/v1/_probe/echo';

beforeEach(function (): void {
    Route::middleware('api')->prefix('api/v1/_probe')->group(function (): void {
        Route::match(['GET', 'POST', 'DELETE'], 'echo', fn (Request $request) => ['data' => $request->all()]);
    });
});

/**
 * @return TestResponse<Response>
 */
function sendRaw(string $content, string $contentType): TestResponse
{
    return call('POST', ECHO_URI, server: ['CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'application/json'], content: $content);
}

it('REQ-7.2 refuses a body that is not declared as JSON', function (string $content, string $contentType): void {
    sendRaw($content, $contentType)
        ->assertStatus(415)
        ->assertExactJson(['error' => ['code' => 'request.not_json']]);
})->with([
    'plain text' => ['hello', 'text/plain'],
    'JSON sent as text' => ['{"amount":1}', 'text/plain'],
    'url-encoded' => ['amount=1', 'application/x-www-form-urlencoded'],
]);

it('REQ-7.2 refuses form fields', function (): void {
    post(ECHO_URI, ['amount' => '1'], ['Accept' => 'application/json'])
        ->assertStatus(415)
        ->assertExactJson(['error' => ['code' => 'request.not_json']]);
});

it('REQ-7.2 refuses an uploaded file', function (): void {
    post(ECHO_URI, ['receipt' => UploadedFile::fake()->create('receipt.pdf', 1)], ['Accept' => 'application/json'])
        ->assertStatus(415)
        ->assertExactJson(['error' => ['code' => 'request.not_json']]);
});

it('REQ-7.2 refuses a JSON content type with an invalid body', function (): void {
    sendRaw('{"amount": ', 'application/json')
        ->assertStatus(415)
        ->assertExactJson(['error' => ['code' => 'request.not_json']]);
});

it('REQ-7.5 refuses valid JSON that is not an object or an array', function (string $content): void {
    sendRaw($content, 'application/json')
        ->assertStatus(415)
        ->assertExactJson(['error' => ['code' => 'request.not_json']]);
})->with(['"abc"', '42', 'true', 'null']);

it('REQ-7.2 accepts a JSON object or array', function (string $content, string $contentType): void {
    sendRaw($content, $contentType)->assertOk();
})->with([
    'object' => ['{"amount":1}', 'application/json'],
    'array' => ['[1,2]', 'application/json'],
    'empty object' => ['{}', 'application/json'],
    'vendor JSON type' => ['{"amount":1}', 'application/vnd.api+json'],
    'charset parameter' => ['{"amount":1}', 'application/json; charset=utf-8'],
]);

it('REQ-7.2 accepts a JSON body sent by the JSON test client', function (): void {
    postJson(ECHO_URI, ['amount' => 1])->assertOk()->assertExactJson(['data' => ['amount' => 1]]);
});

it('REQ-7.3 accepts a request without a body whatever its content type', function (string $method): void {
    $headers = ['Content-Type' => 'text/plain', 'Accept' => 'application/json'];

    $response = $method === 'GET' ? get(ECHO_URI, $headers) : delete(ECHO_URI, [], $headers);

    $response->assertOk();
})->with(['GET', 'DELETE']);
