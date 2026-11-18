<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [
    'stateful' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', ''))),
    )),

    'guard' => ['web'],

    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 129600),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
