<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;
use function Pest\Laravel\json;

it('REQ-2.6 shows the household to its owner (token mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    getJson("/api/v1/households/{$household->id}", HouseholdScenario::as($owner))
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $household->id, 'name' => 'Foyer Sahli', 'role' => 'owner']]);
});

it('REQ-2.6 shows the household to a member (cookie mode)', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    WebBrowser::signedInAs($member)->getJson("/api/v1/households/{$household->id}")
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $household->id, 'name' => 'Foyer Sahli', 'role' => 'member']]);
});

it('REQ-8.3 answers the same 404 for an unknown, malformed, foreign or deleted household', function (string $method, string $suffix): void {
    $caller = User::factory()->create();
    HouseholdScenario::ownedBy($caller);
    $foreign = HouseholdScenario::ownedBy(User::factory()->create());
    $deleted = HouseholdScenario::ownedBy(User::factory()->create());
    $deleted->delete();

    $bodies = [];
    foreach ([(string) Str::uuid(), 'not-a-uuid', $foreign->id, $deleted->id, "'; DROP TABLE households; --"] as $id) {
        $response = json($method, '/api/v1/households/'.rawurlencode($id).$suffix, ['name' => ''], HouseholdScenario::as($caller));
        $response->assertStatus(404)->assertExactJson(['error' => ['code' => 'household.not_found']]);
        $headers = $response->headers->all();
        unset($headers['x-request-id'], $headers['date']);
        $bodies[] = [$response->getContent(), $headers];
    }

    expect(array_unique(array_map('serialize', $bodies)))->toHaveCount(1);
})->with([
    'show' => ['GET', ''],
    'update' => ['PATCH', ''],
    'delete' => ['DELETE', ''],
    'members' => ['GET', '/members'],
]);

it('REQ-8.5 checks authentication before existence', function (string $method, string $suffix): void {
    json($method, '/api/v1/households/'.Str::uuid().$suffix)
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
})->with([
    'show' => ['GET', ''],
    'update' => ['PATCH', ''],
    'delete' => ['DELETE', ''],
    'members' => ['GET', '/members'],
]);
