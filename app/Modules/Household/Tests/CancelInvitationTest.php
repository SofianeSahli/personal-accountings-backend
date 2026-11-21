<?php

declare(strict_types=1);

use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;

it('REQ-2.3 lets the owner cancel a pending invitation (token mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $invitation = HouseholdScenario::invite($household, 'amira@example.com');

    deleteJson("/api/v1/households/{$household->id}/invitations/{$invitation->id}", [], HouseholdScenario::as($owner))
        ->assertNoContent();

    $invitation->refresh();
    expect($invitation->status)->toBe(InvitationStatus::Cancelled)
        ->and($invitation->closed_at)->not->toBeNull();
    getJson("/api/v1/households/{$household->id}/invitations", HouseholdScenario::as($owner))->assertJsonCount(0, 'data');
});

it('REQ-2.3 lets the owner cancel a pending invitation (cookie mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $invitation = HouseholdScenario::invite($household, 'amira@example.com');

    WebBrowser::signedInAs($owner)
        ->deleteJson("/api/v1/households/{$household->id}/invitations/{$invitation->id}")
        ->assertNoContent();
});

it('REQ-2.4 answers the same 404 for unknown, malformed, foreign and closed invitations', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $foreign = HouseholdScenario::invite(HouseholdScenario::ownedBy(User::factory()->create()), 'foreign@example.com');
    $ids = [
        'unknown' => '0193c4d2-1a2b-7c3d-8e4f-5a6b7c8d9e0f',
        'malformed' => 'not-a-uuid',
        'foreign' => $foreign->id,
        'expired' => HouseholdScenario::invite($household, 'a@example.com', HouseholdInvitation::factory()->expired())->id,
        'cancelled' => HouseholdScenario::invite($household, 'b@example.com', HouseholdInvitation::factory()->cancelled())->id,
        'accepted' => HouseholdScenario::invite($household, 'c@example.com', HouseholdInvitation::factory()->accepted())->id,
        'declined' => HouseholdScenario::invite($household, 'd@example.com', HouseholdInvitation::factory()->declined())->id,
    ];

    foreach ($ids as $id) {
        deleteJson("/api/v1/households/{$household->id}/invitations/{$id}", [], HouseholdScenario::as($owner))
            ->assertStatus(404)
            ->assertExactJson(['error' => ['code' => 'invitation.not_found']]);
    }

    expect($foreign->refresh()->status)->toBe(InvitationStatus::Pending);
});

it('REQ-2.4 refuses a member with 403 before looking at the invitation', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);
    $invitation = HouseholdScenario::invite($household, 'amira@example.com');

    deleteJson("/api/v1/households/{$household->id}/invitations/not-a-uuid", [], HouseholdScenario::as($member))
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'household.forbidden']]);
    deleteJson("/api/v1/households/{$household->id}/invitations/{$invitation->id}", [], HouseholdScenario::as($member))
        ->assertStatus(403);

    expect($invitation->refresh()->status)->toBe(InvitationStatus::Pending);
});

it('REQ-8.2 answers 404 for another household', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $invitation = HouseholdScenario::invite($household, 'amira@example.com');

    deleteJson("/api/v1/households/{$household->id}/invitations/{$invitation->id}", [], HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'household.not_found']]);
});

it('REQ-8.1 answers 401 without a session or token', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $invitation = HouseholdScenario::invite($household, 'amira@example.com');

    deleteJson("/api/v1/households/{$household->id}/invitations/{$invitation->id}")->assertStatus(401);
});
