<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\Accounts\FindAccountIdByEmail;
use App\Modules\Identity\Models\User;

it('REQ-8.8 finds the account of an email, in any letter case', function (): void {
    $user = User::factory()->create(['email' => 'amira@example.com']);

    expect(app(FindAccountIdByEmail::class)->handle(' Amira@Example.COM '))->toBe($user->id);
});

it('REQ-8.8 finds nothing for an unknown or empty email', function (): void {
    User::factory()->create(['email' => 'amira@example.com']);

    expect(app(FindAccountIdByEmail::class)->handle('sami@example.com'))->toBeNull()
        ->and(app(FindAccountIdByEmail::class)->handle(''))->toBeNull();
});

it('REQ-8.8 does not match an email that differs only by an accent', function (): void {
    User::factory()->create(['email' => 'andre@example.com']);

    expect(app(FindAccountIdByEmail::class)->handle('andré@example.com'))->toBeNull();
});
