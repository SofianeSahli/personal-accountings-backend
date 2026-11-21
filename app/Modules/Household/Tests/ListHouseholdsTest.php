<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;

use function Pest\Laravel\getJson;

it('REQ-2.5 answers an empty list to a user with no household', function (): void {
    getJson('/api/v1/households', HouseholdScenario::as(User::factory()->create()))
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonPath('meta.next_cursor', null)
        ->assertJsonPath('meta.prev_cursor', null)
        ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['path', 'per_page', 'next_cursor', 'prev_cursor']]);
});

it('REQ-2.5 lists the household of its owner, with the role', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    HouseholdScenario::ownedBy(User::factory()->create(), 'Someone else');

    getJson('/api/v1/households', HouseholdScenario::as($owner))
        ->assertOk()
        ->assertJsonPath('data', [['id' => $household->id, 'name' => 'Foyer Sahli', 'role' => 'owner']]);
});

it('REQ-2.4 gives a member the role member (cookie mode)', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    WebBrowser::signedInAs($member)->getJson('/api/v1/households')
        ->assertOk()
        ->assertJsonPath('data', [['id' => $household->id, 'name' => 'Foyer Sahli', 'role' => 'member']]);
});

it('REQ-8.1 refuses an anonymous caller', function (): void {
    getJson('/api/v1/households')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});
