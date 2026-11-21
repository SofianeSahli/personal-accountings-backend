<?php

declare(strict_types=1);

use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('REQ-4.5 declines an invitation and drops it from both lists (token mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $invitation = HouseholdScenario::invite($household, 'amira@example.com');
    $invitee = User::factory()->create(['email' => 'amira@example.com']);

    postJson("/api/v1/invitations/{$invitation->id}/decline", [], HouseholdScenario::as($invitee))->assertNoContent();

    $invitation->refresh();
    expect($invitation->status)->toBe(InvitationStatus::Declined)
        ->and($invitation->closed_at)->not->toBeNull();
    getJson('/api/v1/invitations', HouseholdScenario::as($invitee))->assertJsonPath('data', []);
    getJson("/api/v1/households/{$household->id}/invitations", HouseholdScenario::as($owner))->assertJsonPath('data', []);
});

it('REQ-4.5 declines an invitation (cookie mode)', function (): void {
    $invitation = HouseholdScenario::invite(HouseholdScenario::ownedBy(User::factory()->create()), 'amira@example.com');
    $invitee = User::factory()->create(['email' => 'amira@example.com']);

    WebBrowser::signedInAs($invitee)->postJson("/api/v1/invitations/{$invitation->id}/decline")->assertNoContent();
});

it('REQ-4.5 declines while I belong to a household', function (): void {
    $invitation = HouseholdScenario::invite(HouseholdScenario::ownedBy(User::factory()->create()), 'amira@example.com');
    $invitee = User::factory()->create(['email' => 'amira@example.com']);
    HouseholdScenario::ownedBy($invitee);

    postJson("/api/v1/invitations/{$invitation->id}/decline", [], HouseholdScenario::as($invitee))->assertNoContent();
});

it('REQ-4.5 answers the same 404 for every invitation I may not answer', function (string $state): void {
    $invitee = User::factory()->create(['email' => 'amira@example.com']);
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $id = match ($state) {
        'unknown' => '0193c4d2-1a2b-7c3d-8e4f-5a6b7c8d9e0f',
        'malformed' => 'not-a-uuid',
        'another address' => HouseholdScenario::invite($household, 'sami@example.com')->id,
        default => HouseholdScenario::invite($household, 'amira@example.com', HouseholdInvitation::factory()->{$state}())->id,
    };

    postJson("/api/v1/invitations/{$id}/decline", [], HouseholdScenario::as($invitee))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'invitation.not_found']]);
})->with(['unknown', 'malformed', 'another address', 'expired', 'cancelled', 'declined', 'accepted']);

it('REQ-4.5 answers 404 while my email is not confirmed', function (): void {
    $invitation = HouseholdScenario::invite(HouseholdScenario::ownedBy(User::factory()->create()), 'amira@example.com');
    $invitee = User::factory()->unverified()->create(['email' => 'amira@example.com']);

    postJson("/api/v1/invitations/{$invitation->id}/decline", [], HouseholdScenario::as($invitee))->assertStatus(404);

    expect($invitation->refresh()->status)->toBe(InvitationStatus::Pending);
});

it('REQ-4.6 refuses to accept a declined invitation', function (): void {
    $invitation = HouseholdScenario::invite(HouseholdScenario::ownedBy(User::factory()->create()), 'amira@example.com');
    $invitee = User::factory()->create(['email' => 'amira@example.com']);

    postJson("/api/v1/invitations/{$invitation->id}/decline", [], HouseholdScenario::as($invitee))->assertNoContent();
    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($invitee))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'invitation.not_found']]);
});

it('REQ-8.1 answers 401 without a session or token', function (): void {
    $invitation = HouseholdScenario::invite(HouseholdScenario::ownedBy(User::factory()->create()), 'amira@example.com');

    postJson("/api/v1/invitations/{$invitation->id}/decline")->assertStatus(401);
});
