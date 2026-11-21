<?php

declare(strict_types=1);

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * @return array{0: Household, 1: HouseholdInvitation}
 */
function invitedHousehold(string $email = 'amira@example.com'): array
{
    $household = HouseholdScenario::ownedBy(User::factory()->create(), 'Foyer Sahli');

    return [$household, HouseholdScenario::invite($household, $email)];
}

it('REQ-4.1 makes me a member and closes the invitation (token mode)', function (): void {
    [$household, $invitation] = invitedHousehold();
    $invitee = User::factory()->create(['email' => 'amira@example.com']);

    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($invitee))
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $household->id, 'name' => 'Foyer Sahli', 'role' => 'member']]);

    $invitation->refresh();
    expect($invitation->status)->toBe(InvitationStatus::Accepted)
        ->and($invitation->closed_at)->not->toBeNull();
});

it('REQ-4.1 makes me a member (cookie mode)', function (): void {
    [$household, $invitation] = invitedHousehold();
    $invitee = User::factory()->create(['email' => 'amira@example.com']);

    WebBrowser::signedInAs($invitee)
        ->postJson("/api/v1/invitations/{$invitation->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.role', 'member');
});

it('REQ-4.7 shows the household and its members to me afterwards', function (): void {
    [$household, $invitation] = invitedHousehold();
    $invitee = User::factory()->create(['email' => 'amira@example.com']);

    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($invitee))->assertOk();

    getJson('/api/v1/households', HouseholdScenario::as($invitee))->assertJsonPath('data.0.id', $household->id);
    getJson("/api/v1/households/{$household->id}/members", HouseholdScenario::as($invitee))
        ->assertJsonPath('data.1.id', $invitee->id)
        ->assertJsonPath('data.1.role', 'member');
    getJson('/api/v1/invitations', HouseholdScenario::as($invitee))->assertJsonPath('data', []);
});

it('REQ-4.2 answers the same 404 for every invitation I may not answer', function (): void {
    $invitee = User::factory()->create(['email' => 'amira@example.com']);
    [, $other] = invitedHousehold('sami@example.com');
    [$deletedHousehold, $ofDeleted] = invitedHousehold();
    $deletedHousehold->delete();
    $ids = [
        'unknown' => '0193c4d2-1a2b-7c3d-8e4f-5a6b7c8d9e0f',
        'malformed' => 'not-a-uuid',
        'another address' => $other->id,
        'household deleted' => $ofDeleted->id,
    ];
    foreach (['expired', 'cancelled', 'declined', 'accepted'] as $state) {
        $ids[$state] = HouseholdScenario::invite(
            HouseholdScenario::ownedBy(User::factory()->create()),
            'amira@example.com',
            HouseholdInvitation::factory()->{$state}(),
        )->id;
    }

    $bodies = [];
    foreach ($ids as $case => $id) {
        $response = postJson("/api/v1/invitations/{$id}/accept", [], HouseholdScenario::as($invitee))->assertStatus(404);
        $bodies[$case] = $response->getContent();
    }

    expect(array_unique($bodies))->toBe(['unknown' => '{"error":{"code":"invitation.not_found"}}'])
        ->and(HouseholdMember::query()->where('user_id', $invitee->id)->count())->toBe(0);
});

it('REQ-4.2 answers 404 while my email is not confirmed', function (): void {
    [, $invitation] = invitedHousehold();
    $invitee = User::factory()->unverified()->create(['email' => 'amira@example.com']);

    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($invitee))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'invitation.not_found']]);
});

it('REQ-4.3 refuses me while I belong to a household, as owner or member, and keeps the invitation', function (bool $asOwner): void {
    [, $invitation] = invitedHousehold();
    $invitee = User::factory()->create(['email' => 'amira@example.com']);
    $asOwner ? HouseholdScenario::ownedBy($invitee) : HouseholdScenario::join(HouseholdScenario::ownedBy(User::factory()->create()), $invitee);

    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($invitee))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'household.already_joined']]);

    expect($invitation->refresh()->status)->toBe(InvitationStatus::Pending);
})->with(['as owner' => true, 'as member' => false]);

it('REQ-4.3 lets a member of a deleted household accept', function (): void {
    [$household, $invitation] = invitedHousehold();
    $invitee = User::factory()->create(['email' => 'amira@example.com']);
    $former = HouseholdScenario::ownedBy($invitee);
    deleteJson("/api/v1/households/{$former->id}", [], HouseholdScenario::as($invitee))->assertNoContent();

    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($invitee))
        ->assertOk()
        ->assertJsonPath('data.id', $household->id);
});

it('REQ-4.3 maps the one-household index to household.already_joined', function (): void {
    [, $invitation] = invitedHousehold();
    $invitee = User::factory()->create(['email' => 'amira@example.com']);
    $stale = HouseholdScenario::ownedBy($invitee);
    Household::query()->whereKey($stale->id)->update(['deleted_at' => now()]);

    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($invitee))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'household.already_joined']]);

    expect($invitation->refresh()->status)->toBe(InvitationStatus::Pending);
});

it('REQ-4.4 refuses when the household has 10 members, and keeps the invitation', function (): void {
    [$household, $invitation] = invitedHousehold();
    foreach (range(1, Household::MEMBER_LIMIT - 1) as $ignored) {
        HouseholdScenario::memberOf($household);
    }
    $invitee = User::factory()->create(['email' => 'amira@example.com']);

    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($invitee))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'household.member_limit']]);

    expect($invitation->refresh()->status)->toBe(InvitationStatus::Pending);
});

it('REQ-4.6 accepts an invitation only once', function (): void {
    [, $invitation] = invitedHousehold();
    $invitee = User::factory()->create(['email' => 'amira@example.com']);

    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($invitee))->assertOk();
    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($invitee))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'invitation.not_found']]);
});

it('REQ-4.7 refuses my other invitations once I have joined', function (): void {
    [, $first] = invitedHousehold();
    [, $second] = invitedHousehold();
    $invitee = User::factory()->create(['email' => 'amira@example.com']);

    postJson("/api/v1/invitations/{$first->id}/accept", [], HouseholdScenario::as($invitee))->assertOk();

    getJson('/api/v1/invitations', HouseholdScenario::as($invitee))->assertJsonPath('data.0.id', $second->id);
    postJson("/api/v1/invitations/{$second->id}/accept", [], HouseholdScenario::as($invitee))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'household.already_joined']]);
});

it('REQ-8.1 answers 401 without a session or token', function (): void {
    [, $invitation] = invitedHousehold();

    postJson("/api/v1/invitations/{$invitation->id}/accept")
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});
