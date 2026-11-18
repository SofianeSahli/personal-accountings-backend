<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Locale;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\IdentityScenario;

use function Pest\Laravel\getJson;

it('REQ-7.1 reads the signed-in account', function (): void {
    $user = User::factory()->create(['phone' => '+21620123456', 'locale' => Locale::En]);

    getJson('/api/v1/me', IdentityScenario::bearer(IdentityScenario::tokenFor($user)))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+21620123456',
            'locale' => 'en',
            'two_factor_enabled' => false,
        ]]);
});

it('REQ-7.1 says whether two-step sign-in is on and never returns a hash', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $response = getJson('/api/v1/me', IdentityScenario::bearer(IdentityScenario::tokenFor($user)));

    $response->assertOk()->assertJsonPath('data.two_factor_enabled', true);
    expect($response->getContent())->not->toContain((string) $user->password);
});

it('REQ-6.7 refuses a missing or invalid session', function (): void {
    getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);

    getJson('/api/v1/me', IdentityScenario::bearer('1|not-a-real-token'))
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});
