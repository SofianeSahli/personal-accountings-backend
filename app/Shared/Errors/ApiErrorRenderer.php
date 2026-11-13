<?php

declare(strict_types=1);

namespace App\Shared\Errors;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class ApiErrorRenderer
{
    private const API_PATHS = 'api/*';

    private const RETRY_AFTER_HEADER = 'Retry-After';

    private const TRACE_FRAME_LIMIT = 50;

    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is(self::API_PATHS)) {
            return null;
        }

        $error = $this->describe($exception);

        return new JsonResponse(
            ['error' => $this->body($error, $exception)],
            $error->status,
            $error->headers,
        );
    }

    private function describe(Throwable $exception): ApiException
    {
        return match (true) {
            $exception instanceof ApiException => $exception,
            $exception instanceof ValidationException => new ApiException(
                'validation.failed',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ValidationCodes::from($exception->validator),
            ),
            $exception instanceof AuthenticationException => new ApiException('auth.unauthenticated', Response::HTTP_UNAUTHORIZED),
            $exception instanceof AuthorizationException && $exception->status() === Response::HTTP_NOT_FOUND => $this->notFound(),
            $exception instanceof AuthorizationException,
            $exception instanceof AccessDeniedHttpException => new ApiException('auth.forbidden', Response::HTTP_FORBIDDEN),
            $exception instanceof ModelNotFoundException,
            $exception instanceof RecordsNotFoundException,
            $exception instanceof NotFoundHttpException => $this->notFound(),
            $exception instanceof MethodNotAllowedHttpException => new ApiException(
                'request.method_not_allowed',
                Response::HTTP_METHOD_NOT_ALLOWED,
                headers: $this->headersOf($exception),
            ),
            $exception instanceof TooManyRequestsHttpException => $this->throttled($exception),
            $exception instanceof HttpExceptionInterface
                && $exception->getStatusCode() === Response::HTTP_SERVICE_UNAVAILABLE => new ApiException(
                    'server.unavailable',
                    Response::HTTP_SERVICE_UNAVAILABLE,
                    headers: $this->headersOf($exception),
                ),
            default => new ApiException('server.error', Response::HTTP_INTERNAL_SERVER_ERROR),
        };
    }

    private function notFound(): ApiException
    {
        return new ApiException('request.not_found', Response::HTTP_NOT_FOUND);
    }

    private function throttled(TooManyRequestsHttpException $exception): ApiException
    {
        $headers = $this->headersOf($exception);
        $retryAfter = $headers[self::RETRY_AFTER_HEADER] ?? null;

        return new ApiException(
            'request.throttled',
            Response::HTTP_TOO_MANY_REQUESTS,
            retryAfterSeconds: is_numeric($retryAfter) ? (int) $retryAfter : null,
            headers: $headers,
        );
    }

    /**
     * @return array<string, string>
     */
    private function headersOf(HttpExceptionInterface $exception): array
    {
        $headers = [];

        foreach ($exception->getHeaders() as $name => $value) {
            $headers[(string) $name] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(ApiException $error, Throwable $exception): array
    {
        $body = ['code' => $error->errorCode];

        if ($error->fields !== []) {
            $body['fields'] = $error->fields;
        }

        if ($error->retryAfterSeconds !== null) {
            $body['retry_after_seconds'] = $error->retryAfterSeconds;
        }

        if ($error->status === Response::HTTP_INTERNAL_SERVER_ERROR && config('app.debug') === true) {
            $body['debug'] = $this->debug($exception);
        }

        return $body;
    }

    /**
     * @return array{exception: class-string, message: string, file: string, line: int, trace: list<string>}
     */
    private function debug(Throwable $exception): array
    {
        $frames = array_slice($exception->getTrace(), 0, self::TRACE_FRAME_LIMIT);

        return [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => array_map(
                fn (array $frame): string => ($frame['file'] ?? '[internal]').':'.($frame['line'] ?? 0),
                $frames,
            ),
        ];
    }
}
