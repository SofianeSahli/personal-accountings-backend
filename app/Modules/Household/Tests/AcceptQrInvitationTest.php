<?php

declare(strict_types=1);

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('REQ-10.2 makes me a member and uses up the code (token mode)', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create(), 'Foyer Sahli');
    $qr = HouseholdScenario::qrInvitation($household, 'scan-me');
    $scanner = User::factory()->create();

    postJson('/api/v1/qr-invitations/accept', ['token' => 'scan-me'], HouseholdScenario::as($scanner))
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $household->id, 'name' => 'Foyer Sahli', 'role' => 'member']]);

    $qr->refresh();
    expect($qr->status)->toBe(InvitationStatus::Accepted)
        ->and($qr->closed_at)->not->toBeNull();
    getJson("/api/v1/households/{$household->id}", HouseholdScenario::as($scanner))->assertOk();
});

it('REQ-10.2 makes me a member (cookie mode)', function (): void {
    HouseholdScenario::qrInvitation(HouseholdScenario::ownedBy(User::factory()->create()), 'scan-me');

    WebBrowser::signedInAs(User::factory()->create())
        ->postJson('/api/v1/qr-invitations/accept', ['token' => 'scan-me'])
        ->assertOk()
        ->assertJsonPath('data.role', 'member');
});

it('REQ-10.3 works only once', function (): void {
    HouseholdScenario::qrInvitation(HouseholdScenario::ownedBy(User::factory()->create()), 'scan-me');

    postJson('/api/v1/qr-invitations/accept', ['token' => 'scan-me'], HouseholdScenario::as(User::factory()->create()))->assertOk();
    postJson('/api/v1/qr-invitations/accept', ['token' => 'scan-me'], HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'invitation.not_found']]);
});

it('REQ-10.3 answers 404 for an expired code or an unconfirmed email', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    HouseholdScenario::qrInvitation($household, 'expired', HouseholdQrInvitation::factory()->expired());
    HouseholdScenario::qrInvitation($household, 'fresh');

    postJson('/api/v1/qr-invitations/accept', ['token' => 'expired'], HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(404);
    postJson('/api/v1/qr-invitations/accept', ['token' => 'fresh'], HouseholdScenario::as(User::factory()->unverified()->create()))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'invitation.not_found']]);
});

it('REQ-10.2 refuses me while I belong to a household, and keeps the code valid', function (): void {
    $qr = HouseholdScenario::qrInvitation(HouseholdScenario::ownedBy(User::factory()->create()), 'scan-me');
    $scanner = User::factory()->create();
    HouseholdScenario::ownedBy($scanner);

    postJson('/api/v1/qr-invitations/accept', ['token' => 'scan-me'], HouseholdScenario::as($scanner))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'household.already_joined']]);

    expect($qr->refresh()->status)->toBe(InvitationStatus::Pending);
});

it('REQ-10.2 refuses when the household has 10 members, and keeps the code valid', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    foreach (range(1, Household::MEMBER_LIMIT - 1) as $ignored) {
        HouseholdScenario::memberOf($household);
    }
    $qr = HouseholdScenario::qrInvitation($household, 'scan-me');

    postJson('/api/v1/qr-invitations/accept', ['token' => 'scan-me'], HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'household.member_limit']]);

    expect($qr->refresh()->status)->toBe(InvitationStatus::Pending);
});

it('REQ-10.4 limits preview and join together to 10 a minute per account', function (): void {
    $scanner = User::factory()->create();
    foreach (range(1, 5) as $ignored) {
        postJson('/api/v1/qr-invitations/preview', ['token' => 'nothing'], HouseholdScenario::as($scanner))->assertStatus(404);
        postJson('/api/v1/qr-invitations/accept', ['token' => 'nothing'], HouseholdScenario::as($scanner))->assertStatus(404);
    }

    postJson('/api/v1/qr-invitations/accept', ['token' => 'nothing'], HouseholdScenario::as($scanner))
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled')
        ->assertHeader('Retry-After');
    postJson('/api/v1/qr-invitations/preview', ['token' => 'nothing'], HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(404);
});

it('REQ-8.1 answers 401 without a session or token', function (): void {
    postJson('/api/v1/qr-invitations/accept', ['token' => 'scan-me'])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});
