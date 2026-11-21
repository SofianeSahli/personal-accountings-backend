<?php

declare(strict_types=1);

use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdQrInvitation;
use App\Modules\Household\Models\InvitationStatus;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\freezeSecond;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('REQ-9.1 gives the owner a QR token valid for 10 minutes (token mode)', function (): void {
    freezeSecond();
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    $response = postJson("/api/v1/households/{$household->id}/qr-invitations", [], HouseholdScenario::as($owner))
        ->assertCreated();

    $token = (string) $response->json('data.token');
    $qr = HouseholdQrInvitation::query()->sole();
    $response->assertExactJson(['data' => [
        'id' => $qr->id,
        'token' => $token,
        'expires_at' => now()->addMinutes(10)->toIso8601ZuluString(),
    ]]);
    expect($token)->toMatch('/^[A-Za-z0-9_-]{43}$/')
        ->and($qr->status)->toBe(InvitationStatus::Pending)
        ->and($qr->invited_by)->toBe($owner->id);
});

it('REQ-9.1 gives the owner a QR token (cookie mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    WebBrowser::signedInAs($owner)
        ->postJson("/api/v1/households/{$household->id}/qr-invitations")
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'token', 'expires_at']]);
});

it('REQ-9.2 stores only the hash of the token', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    $token = (string) postJson("/api/v1/households/{$household->id}/qr-invitations", [], HouseholdScenario::as($owner))
        ->json('data.token');

    $row = (array) DB::table('household_qr_invitations')->sole();
    expect($row['token_hash'])->toBe(hash('sha256', $token))
        ->and(implode(' ', array_map('strval', $row)))->not->toContain($token);
});

it('REQ-9.3 cancels the previous unused QR invitation', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $old = HouseholdScenario::qrInvitation($household, 'old-token');

    postJson("/api/v1/households/{$household->id}/qr-invitations", [], HouseholdScenario::as($owner))->assertCreated();

    expect($old->refresh()->status)->toBe(InvitationStatus::Cancelled)
        ->and(HouseholdQrInvitation::query()->pending()->where('household_id', $household->id)->count())->toBe(1);
});

it('REQ-9.4 refuses when the household has 10 members, and changes nothing', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    foreach (range(1, Household::MEMBER_LIMIT - 1) as $ignored) {
        HouseholdScenario::memberOf($household);
    }
    $old = HouseholdScenario::qrInvitation($household, 'old-token');

    postJson("/api/v1/households/{$household->id}/qr-invitations", [], HouseholdScenario::as($owner))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'household.member_limit']]);

    expect($old->refresh()->status)->toBe(InvitationStatus::Pending)
        ->and(HouseholdQrInvitation::query()->count())->toBe(1);
});

it('REQ-9.5 refuses a member with 403', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    postJson("/api/v1/households/{$household->id}/qr-invitations", [], HouseholdScenario::as($member))
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'household.forbidden']]);
});

it('REQ-9.6 keeps QR invitations out of the email invitation lists', function (): void {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $household = HouseholdScenario::ownedBy($owner);

    postJson("/api/v1/households/{$household->id}/qr-invitations", [], HouseholdScenario::as($owner))->assertCreated();

    getJson("/api/v1/households/{$household->id}/invitations", HouseholdScenario::as($owner))->assertJsonPath('data', []);
    getJson('/api/v1/invitations', HouseholdScenario::as($owner))->assertJsonPath('data', []);
});

it('REQ-9.1 shares the creations limit, before looking at the household', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    foreach (range(1, 10) as $ignored) {
        postJson("/api/v1/households/{$household->id}/qr-invitations", [], HouseholdScenario::as($owner))->assertCreated();
    }

    postJson('/api/v1/households/0193c4d2-1a2b-7c3d-8e4f-5a6b7c8d9e0f/qr-invitations', [], HouseholdScenario::as($owner))
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});

it('REQ-8.2 answers 404 for another household', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());

    postJson("/api/v1/households/{$household->id}/qr-invitations", [], HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'household.not_found']]);
});

it('REQ-8.1 answers 401 without a session or token', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());

    postJson("/api/v1/households/{$household->id}/qr-invitations")->assertStatus(401);
});
