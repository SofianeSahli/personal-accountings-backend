<?php

declare(strict_types=1);

use App\Modules\Identity\Support\AuthTestMode;

it('REQ-10.2 is off by default', function (): void {
    expect(AuthTestMode::isOn())->toBeFalse();
});

it('REQ-10.1 is on when configured outside production', function (): void {
    config(['identity.auth_test_mode' => true]);

    expect(AuthTestMode::isOn())->toBeTrue();
});

it('REQ-10.2 refuses to turn on in production', function (): void {
    config(['identity.auth_test_mode' => true]);
    app()->detectEnvironment(fn (): string => 'production');

    expect(AuthTestMode::isOn())->toBeFalse();
});
