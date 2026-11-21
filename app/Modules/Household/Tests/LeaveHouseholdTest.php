<?php

declare(strict_types=1);

use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('REQ-6.1 lets a member leave, keeping the row soft-deleted (token mode)', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    postJson("/api/v1/households/{$household->id}/leave", [], HouseholdScenario::as($member))->assertNoContent();

    expect(HouseholdMember::query()->where('user_id', $member->id)->count())->toBe(0)
        ->and(HouseholdMember::withTrashed()->where('user_id', $member->id)->count())->toBe(1);
});

it('REQ-6.1 lets a member leave (cookie mode)', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    WebBrowser::signedInAs($member)->postJson("/api/v1/households/{$household->id}/leave")->assertNoContent();
});

it('REQ-6.2 refuses the owner, and changes nothing', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    postJson("/api/v1/households/{$household->id}/leave", [], HouseholdScenario::as($owner))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'household.owner_cannot_leave']]);

    getJson("/api/v1/households/{$household->id}", HouseholdScenario::as($owner))->assertOk()->assertJsonPath('data.role', 'owner');
});

it('REQ-6.3 cuts me off at once', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    postJson("/api/v1/households/{$household->id}/leave", [], HouseholdScenario::as($member))->assertNoContent();

    getJson("/api/v1/households/{$household->id}", HouseholdScenario::as($member))->assertStatus(404);
    getJson('/api/v1/households', HouseholdScenario::as($member))->assertJsonPath('data', []);
    postJson("/api/v1/households/{$household->id}/leave", [], HouseholdScenario::as($member))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'household.not_found']]);
});

it('REQ-6.4 frees me to accept another invitation', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = User::factory()->create(['email' => 'amira@example.com']);
    HouseholdScenario::join($household, $member);
    $other = HouseholdScenario::ownedBy(User::factory()->create());
    $invitation = HouseholdScenario::invite($other, 'amira@example.com');

    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($member))->assertStatus(422);
    postJson("/api/v1/households/{$household->id}/leave", [], HouseholdScenario::as($member))->assertNoContent();
    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($member))
        ->assertOk()
        ->assertJsonPath('data.id', $other->id);
});

it('REQ-8.2 answers 404 for another household', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());

    postJson("/api/v1/households/{$household->id}/leave", [], HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'household.not_found']]);
});

it('REQ-8.1 answers 401 without a session or token', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());

    postJson("/api/v1/households/{$household->id}/leave")->assertStatus(401);
});
