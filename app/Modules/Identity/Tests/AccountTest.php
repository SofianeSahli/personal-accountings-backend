<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Locale;
use App\Modules\Identity\Models\User;

it('REQ-7.1 reads the signed-in account', function () {
    $user = User::factory()->create(['phone' => '+21620123456', 'locale' => Locale::En]);
    $token = $user->createToken(fakeDeviceId())->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')
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

it('REQ-7.1 says whether two-step sign-in is on and never returns a hash', function () {
    $user = User::factory()->withTwoFactor()->create();
    $token = $user->createToken(fakeDeviceId())->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/me');

    $response->assertOk()->assertJsonPath('data.two_factor_enabled', true);
    expect($response->getContent())->not->toContain($user->password);
});

it('REQ-6.7 refuses a missing or invalid session', function () {
    $this->getJson('/api/v1/me')->assertStatus(401)->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);

    $this->withToken('1|not-a-real-token')->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});
