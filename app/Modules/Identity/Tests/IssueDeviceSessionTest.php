<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\Sessions\IssueDeviceSession;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;

it('REQ-6.3 keeps one token per account and device', function (): void {
    $user = User::factory()->create();
    $deviceId = IdentityScenario::deviceId();

    $first = app(IssueDeviceSession::class)->handle($user, $deviceId);
    $second = app(IssueDeviceSession::class)->handle($user, $deviceId);

    expect($first)->not->toBe($second);
    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->sole()->name)->toBe($deviceId);
});

it('REQ-6.3 leaves the tokens of other devices and other accounts alone', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $deviceId = IdentityScenario::deviceId();

    app(IssueDeviceSession::class)->handle($otherUser, $deviceId);
    app(IssueDeviceSession::class)->handle($user, IdentityScenario::deviceId());
    app(IssueDeviceSession::class)->handle($user, $deviceId);
    app(IssueDeviceSession::class)->handle($user, $deviceId);

    expect($user->tokens()->count())->toBe(2);
    expect($otherUser->tokens()->count())->toBe(1);
});

it('REQ-6.3 treats a device id in capitals as the same device', function (): void {
    $user = User::factory()->create();
    $deviceId = IdentityScenario::deviceId();

    app(IssueDeviceSession::class)->handle($user, $deviceId);
    app(IssueDeviceSession::class)->handle($user, strtoupper($deviceId));

    expect($user->tokens()->count())->toBe(1);
});

it('REQ-6.4 leaves the expiry to the global token lifetime', function (): void {
    $user = User::factory()->create();

    app(IssueDeviceSession::class)->handle($user, IdentityScenario::deviceId());

    expect($user->tokens()->sole()->expires_at)->toBeNull();
    expect(config('sanctum.expiration'))->toBe(129600);
});
