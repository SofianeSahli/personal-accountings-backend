<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;

use function Pest\Laravel\patchJson;

it('REQ-2.7 lets the owner rename the household (token mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    patchJson("/api/v1/households/{$household->id}", ['name' => ' Famille Sahli '], HouseholdScenario::as($owner))
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $household->id, 'name' => 'Famille Sahli', 'role' => 'owner']]);

    expect($household->fresh()?->name)->toBe('Famille Sahli');
});

it('REQ-2.7 lets the owner rename the household (cookie mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    WebBrowser::signedInAs($owner)->patchJson("/api/v1/households/{$household->id}", ['name' => 'Famille'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Famille');
});

it('REQ-2.7 changes nothing for an empty body', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    patchJson("/api/v1/households/{$household->id}", [], HouseholdScenario::as($owner))
        ->assertOk()
        ->assertJsonPath('data.name', 'Foyer Sahli');
});

it('REQ-0.1 validates a name that is sent', function (mixed $name, string $code): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    patchJson("/api/v1/households/{$household->id}", ['name' => $name], HouseholdScenario::as($owner))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['name' => [$code]]]]);

    expect($household->fresh()?->name)->toBe('Foyer Sahli');
})->with([
    'empty' => ['', 'name.required'],
    'null' => [null, 'name.required'],
    'not a string' => [['x'], 'name.string'],
    'too long' => [str_repeat('a', 61), 'name.max'],
]);

it('REQ-2.7 refuses a member whatever the body, before validation (REQ-8.5)', function (array $body): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    patchJson("/api/v1/households/{$household->id}", $body, HouseholdScenario::as($member))
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'household.forbidden']]);

    expect($household->fresh()?->name)->toBe('Foyer Sahli');
})->with([
    'valid' => [['name' => 'Mine now']],
    'invalid' => [['name' => '']],
    'empty' => [[]],
]);
