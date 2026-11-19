<?php

declare(strict_types=1);

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Models\HouseholdRole;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

it('REQ-2.1 creates a household with the caller as its owner (token mode)', function (): void {
    $user = User::factory()->create();

    $response = postJson('/api/v1/households', ['name' => '  Foyer Sahli  '], HouseholdScenario::as($user));

    $household = Household::query()->sole();
    $response->assertCreated()->assertExactJson(['data' => [
        'id' => $household->id,
        'name' => 'Foyer Sahli',
        'role' => 'owner',
    ]]);
    $member = HouseholdMember::query()->sole();
    expect($member->user_id)->toBe($user->id)
        ->and($member->household_id)->toBe($household->id)
        ->and($member->role)->toBe(HouseholdRole::Owner);
});

it('REQ-2.1 creates a household in cookie mode', function (): void {
    $user = User::factory()->create();
    $browser = WebBrowser::signedInAs($user);

    $browser->postJson('/api/v1/households', ['name' => 'Foyer'])
        ->assertCreated()
        ->assertJsonPath('data.role', 'owner');

    expect(HouseholdMember::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('REQ-0.1 validates the name', function (mixed $name, string $code): void {
    $user = User::factory()->create();

    postJson('/api/v1/households', ['name' => $name], HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['name' => [$code]]]]);

    expect(Household::query()->count())->toBe(0);
})->with([
    'missing' => [null, 'name.required'],
    'blank' => ['   ', 'name.required'],
    'not a string' => [42, 'name.string'],
    'too long' => [str_repeat('é', 61), 'name.max'],
]);

it('REQ-0.1 accepts a 60-character name that need not be unique', function (): void {
    $name = str_repeat('é', 60);
    HouseholdScenario::ownedBy(User::factory()->create(), $name);

    postJson('/api/v1/households', ['name' => $name], HouseholdScenario::as(User::factory()->create()))
        ->assertCreated()
        ->assertJsonPath('data.name', $name);
});

it('REQ-2.2 refuses a second household to a user who already belongs to one', function (HouseholdRole $role): void {
    $user = User::factory()->create();
    HouseholdScenario::join(Household::factory()->create(), $user, $role);

    postJson('/api/v1/households', ['name' => 'Another'], HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'household.limit_reached']]);

    expect(Household::query()->count())->toBe(1);
})->with([HouseholdRole::Owner, HouseholdRole::Member]);

it('REQ-2.2 keeps one household per user when a concurrent request wins the race', function (): void {
    $user = User::factory()->create();
    $rival = Household::factory()->create();
    HouseholdMember::creating(function (HouseholdMember $member) use ($user, $rival): void {
        if ($member->household_id !== $rival->id) {
            DB::table('household_user')->insert([
                'id' => (string) Str::uuid(),
                'household_id' => $rival->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    postJson('/api/v1/households', ['name' => 'Second'], HouseholdScenario::as($user))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'household.limit_reached']]);

    expect(Household::query()->count())->toBe(1);
});

it('REQ-8.1 refuses an anonymous caller', function (): void {
    postJson('/api/v1/households', ['name' => 'Foyer'])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('REQ-2.8 limits creations to 10 a minute per account', function (): void {
    $headers = HouseholdScenario::as(User::factory()->create());

    for ($attempt = 0; $attempt < 10; $attempt++) {
        expect(postJson('/api/v1/households', ['name' => 'Foyer'], $headers)->status())->not->toBe(429);
    }

    postJson('/api/v1/households', ['name' => 'Foyer'], $headers)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled')
        ->assertJsonStructure(['error' => ['code', 'retry_after_seconds']]);

    postJson('/api/v1/households', ['name' => 'Foyer'], HouseholdScenario::as(User::factory()->create()))
        ->assertCreated();
});
