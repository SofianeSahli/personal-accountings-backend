<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Locale;
use App\Modules\Identity\Models\User;

it('REQ-8.8 gives the confirmed email in lower case', function (): void {
    $user = User::factory()->make(['email' => 'Amira.Ben@Example.com']);

    expect($user->verifiedEmail())->toBe('amira.ben@example.com');
});

it('REQ-8.8 gives no verified email while the email is not confirmed', function (): void {
    expect(User::factory()->unverified()->make()->verifiedEmail())->toBeNull();
});

it('REQ-8.8 gives the account locale code', function (): void {
    expect(User::factory()->make(['locale' => Locale::En])->localeCode())->toBe('en')
        ->and(User::factory()->make(['locale' => Locale::Fr])->localeCode())->toBe('fr');
});
