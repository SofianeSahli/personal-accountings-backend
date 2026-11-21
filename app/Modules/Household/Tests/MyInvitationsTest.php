<?php

declare(strict_types=1);

use App\Modules\Household\Actions\CancelInvitation;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;

use function Pest\Laravel\getJson;

it('REQ-3.1 lists the pending invitations sent to my verified email, in any letter case (token mode)', function (): void {
    $owner = User::factory()->create(['name' => 'Sofiane']);
    $household = HouseholdScenario::ownedBy($owner, 'Foyer Sahli');
    $invitee = User::factory()->create(['email' => 'Amira@Example.com']);
    $invitation = HouseholdScenario::invite($household, 'amira@example.com');

    getJson('/api/v1/invitations', HouseholdScenario::as($invitee))
        ->assertOk()
        ->assertJsonPath('data', [[
            'id' => $invitation->id,
            'household' => ['id' => $household->id, 'name' => 'Foyer Sahli'],
            'invited_by' => ['name' => 'Sofiane'],
            'created_at' => $invitation->created_at?->toIso8601ZuluString(),
            'expires_at' => $invitation->expires_at->toIso8601ZuluString(),
        ]]);
});

it('REQ-3.1 lists my pending invitations (cookie mode)', function (): void {
    $invitee = User::factory()->create(['email' => 'amira@example.com']);
    HouseholdScenario::invite(HouseholdScenario::ownedBy(User::factory()->create()), 'amira@example.com');

    WebBrowser::signedInAs($invitee)->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(1, 'data');
});

it('REQ-3.1 leaves out other addresses and invitations that are not pending', function (): void {
    $invitee = User::factory()->create(['email' => 'amira@example.com']);
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $deleted = HouseholdScenario::ownedBy(User::factory()->create());
    HouseholdScenario::invite($household, 'sami@example.com');
    HouseholdScenario::invite($household, 'amira@example.com', HouseholdInvitation::factory()->expired());
    HouseholdScenario::invite($household, 'amira@example.com', HouseholdInvitation::factory()->accepted());
    HouseholdScenario::invite($household, 'amira@example.com', HouseholdInvitation::factory()->declined());
    $cancelled = HouseholdScenario::invite($household, 'amira@example.com');
    app(CancelInvitation::class)->handle($household, $cancelled->id);
    HouseholdScenario::invite($deleted, 'amira@example.com');
    $deleted->delete();

    getJson('/api/v1/invitations', HouseholdScenario::as($invitee))->assertOk()->assertJsonPath('data', []);
});

it('REQ-3.2 shows nothing while my email is not confirmed', function (): void {
    $invitee = User::factory()->unverified()->create(['email' => 'amira@example.com']);
    HouseholdScenario::invite(HouseholdScenario::ownedBy(User::factory()->create()), 'amira@example.com');

    getJson('/api/v1/invitations', HouseholdScenario::as($invitee))->assertOk()->assertJsonPath('data', []);
});

it('REQ-3.3 shows the inviter\'s current name and no email address', function (): void {
    $owner = User::factory()->create(['name' => 'Old Name', 'email' => 'owner.private@example.com']);
    $invitee = User::factory()->create(['email' => 'amira@example.com']);
    HouseholdScenario::invite(HouseholdScenario::ownedBy($owner), 'amira@example.com');
    $owner->update(['name' => 'New Name']);

    $response = getJson('/api/v1/invitations', HouseholdScenario::as($invitee))
        ->assertOk()
        ->assertJsonPath('data.0.invited_by', ['name' => 'New Name']);

    expect($response->getContent())->not->toContain('@example.com');
});

it('REQ-3.4 still lists an invitation while I belong to a household', function (): void {
    $invitee = User::factory()->create(['email' => 'amira@example.com']);
    HouseholdScenario::ownedBy($invitee);
    HouseholdScenario::invite(HouseholdScenario::ownedBy(User::factory()->create()), 'amira@example.com');

    getJson('/api/v1/invitations', HouseholdScenario::as($invitee))->assertOk()->assertJsonCount(1, 'data');
});

it('REQ-8.1 answers 401 without a session or token', function (): void {
    getJson('/api/v1/invitations')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});
