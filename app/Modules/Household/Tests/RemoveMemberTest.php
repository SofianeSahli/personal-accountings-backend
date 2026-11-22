<?php

declare(strict_types=1);

use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('REQ-5.1 lets the owner remove a member, keeping the row soft-deleted (token mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $member = HouseholdScenario::memberOf($household);

    deleteJson("/api/v1/households/{$household->id}/members/{$member->id}", [], HouseholdScenario::as($owner))
        ->assertNoContent();

    expect(HouseholdMember::query()->where('user_id', $member->id)->count())->toBe(0)
        ->and(HouseholdMember::withTrashed()->where('user_id', $member->id)->whereNotNull('deleted_at')->count())->toBe(1);
    getJson("/api/v1/households/{$household->id}/members", HouseholdScenario::as($owner))->assertJsonCount(1, 'data');
});

it('REQ-5.1 lets the owner remove a member (cookie mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $member = HouseholdScenario::memberOf($household);

    WebBrowser::signedInAs($owner)
        ->deleteJson("/api/v1/households/{$household->id}/members/{$member->id}")
        ->assertNoContent();
});

it('REQ-6.3 and REQ-6.4 cut the removed member off at once and free them', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $member = HouseholdScenario::memberOf($household);

    deleteJson("/api/v1/households/{$household->id}/members/{$member->id}", [], HouseholdScenario::as($owner))->assertNoContent();

    getJson("/api/v1/households/{$household->id}", HouseholdScenario::as($member))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'household.not_found']]);
    getJson('/api/v1/households', HouseholdScenario::as($member))->assertJsonPath('data', []);
    postJson('/api/v1/households', ['name' => 'Mine'], HouseholdScenario::as($member))->assertCreated();
});

it('REQ-6.4 lets the owner invite a removed member again', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $member = User::factory()->create(['email' => 'amira@example.com']);
    HouseholdScenario::join($household, $member);

    deleteJson("/api/v1/households/{$household->id}/members/{$member->id}", [], HouseholdScenario::as($owner))->assertNoContent();

    postJson("/api/v1/households/{$household->id}/invitations", ['email' => 'amira@example.com'], HouseholdScenario::as($owner))
        ->assertCreated();
});

it('REQ-5.2 answers the same 404 for unknown, malformed, foreign and removed members', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $foreign = HouseholdScenario::memberOf(HouseholdScenario::ownedBy(User::factory()->create()));
    $removed = HouseholdScenario::memberOf($household);
    HouseholdScenario::leave($removed);
    $ids = ['0193c4d2-1a2b-7c3d-8e4f-5a6b7c8d9e0f', 'not-a-uuid', $foreign->id, $removed->id];

    foreach ($ids as $id) {
        deleteJson("/api/v1/households/{$household->id}/members/{$id}", [], HouseholdScenario::as($owner))
            ->assertStatus(404)
            ->assertExactJson(['error' => ['code' => 'member.not_found']]);
    }

    expect(HouseholdMember::query()->where('user_id', $foreign->id)->count())->toBe(1);
});

it('REQ-5.3 refuses to remove the owner', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    deleteJson("/api/v1/households/{$household->id}/members/{$owner->id}", [], HouseholdScenario::as($owner))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'household.owner_cannot_leave']]);

    expect(HouseholdMember::query()->where('user_id', $owner->id)->count())->toBe(1);
});

it('REQ-5.4 refuses a member with 403, whatever the target', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $member = HouseholdScenario::memberOf($household);
    $other = HouseholdScenario::memberOf($household);

    foreach ([$other->id, $owner->id, 'not-a-uuid'] as $id) {
        deleteJson("/api/v1/households/{$household->id}/members/{$id}", [], HouseholdScenario::as($member))
            ->assertStatus(403)
            ->assertExactJson(['error' => ['code' => 'household.forbidden']]);
    }

    expect(HouseholdMember::query()->where('household_id', $household->id)->count())->toBe(3);
});

it('REQ-8.2 answers 404 for another household', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    deleteJson("/api/v1/households/{$household->id}/members/{$member->id}", [], HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'household.not_found']]);
});

it('REQ-8.1 answers 401 without a session or token', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    deleteJson("/api/v1/households/{$household->id}/members/{$member->id}")->assertStatus(401);
});
