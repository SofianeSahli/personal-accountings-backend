<?php

declare(strict_types=1);

use App\Modules\Identity\Tests\Support\IdentityScenario;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\postJson;

it('REQ-8.4 writes no email, name, phone, password, code or token to the logs', function (): void {
    Mail::fake();
    config(['identity.auth_test_mode' => true]);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    postJson('/api/v1/auth/email/register', [
        'name' => 'Amira Secret-Name',
        'email' => 'amira.private@example.com',
        'password' => 'a very private password',
        'phone' => '+216 20 999 888',
        'terms_version' => config('identity.terms_version'),
    ])->assertStatus(202);
    $signUpCode = IdentityScenario::latestMailedCode();
    $token = (string) postJson('/api/v1/auth/email/confirm', [
        'email' => 'amira.private@example.com',
        'code' => $signUpCode,
        'device_id' => IdentityScenario::deviceId(),
    ])->assertOk()->json('data.token');
    postJson('/api/v1/me/two-factor', [], IdentityScenario::bearer($token))->assertNoContent();
    postJson('/api/v1/auth/email/login', [
        'email' => 'amira.private@example.com',
        'password' => 'a wrong private password',
        'device_id' => IdentityScenario::deviceId(),
    ])->assertStatus(401);
    $pendingSignIn = (string) postJson('/api/v1/auth/email/login', [
        'email' => 'amira.private@example.com',
        'password' => 'a very private password',
        'device_id' => IdentityScenario::deviceId(),
    ])->json('data.pending_sign_in');
    $signInCode = IdentityScenario::latestMailedCode();
    postJson('/api/v1/auth/two-factor', ['pending_sign_in' => $pendingSignIn, 'code' => $signInCode])->assertOk();
    postJson('/api/v1/auth/password/forgot', ['email' => 'amira.private@example.com'])->assertStatus(202);
    $resetCode = IdentityScenario::latestMailedCode();
    postJson('/api/v1/auth/password/reset', [
        'email' => 'amira.private@example.com',
        'code' => $resetCode,
        'password' => 'another private password',
    ])->assertNoContent();

    $secrets = [
        'Secret-Name',
        'amira.private@example.com',
        '+21620999888',
        'a very private password',
        'a wrong private password',
        'another private password',
        $signUpCode,
        $signInCode,
        $resetCode,
        $pendingSignIn,
        $token,
    ];

    foreach ($logged as $line) {
        foreach ($secrets as $secret) {
            expect($line)->not->toContain($secret);
        }
    }
});
