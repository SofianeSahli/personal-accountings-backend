<?php

declare(strict_types=1);

use App\Modules\Household\Actions\ListHouseholdInvitations;
use App\Modules\Household\Models\HouseholdInvitation;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;

use function Pest\Laravel\getJson;
use function Pest\Laravel\travel;

it('REQ-2.1 lists only the household\'s pending invitations, oldest first (token mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $first = HouseholdScenario::invite($household, 'first@example.com');
    travel(1)->minutes();
    $second = HouseholdScenario::invite($household, 'second@example.com');
    HouseholdScenario::invite($household, 'expired@example.com', HouseholdInvitation::factory()->expired());
    HouseholdScenario::invite($household, 'cancelled@example.com', HouseholdInvitation::factory()->cancelled());
    HouseholdScenario::invite($household, 'accepted@example.com', HouseholdInvitation::factory()->accepted());
    HouseholdScenario::invite($household, 'declined@example.com', HouseholdInvitation::factory()->declined());
    HouseholdScenario::invite(HouseholdScenario::ownedBy(User::factory()->create()), 'other@example.com');

    getJson("/api/v1/households/{$household->id}/invitations", HouseholdScenario::as($owner))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$first->id, $second->id])
        ->assertJsonPath('data.0.email', 'first@example.com')
        ->assertJsonPath('data.0.expires_at', $first->expires_at->toIso8601ZuluString());
});

it('REQ-2.1 lists the pending invitations (cookie mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    HouseholdScenario::invite($household, 'amira@example.com');

    WebBrowser::signedInAs($owner)
        ->getJson("/api/v1/households/{$household->id}/invitations")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('REQ-2.1 pages with a cursor', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    foreach (range(1, ListHouseholdInvitations::PER_PAGE + 1) as $index) {
        HouseholdInvitation::factory()->create(['household_id' => $household->id, 'invited_by' => $owner->id]);
    }

    $page = getJson("/api/v1/households/{$household->id}/invitations", HouseholdScenario::as($owner))
        ->assertOk()
        ->assertJsonCount(ListHouseholdInvitations::PER_PAGE, 'data');
    $cursor = (string) $page->json('meta.next_cursor');

    getJson("/api/v1/households/{$household->id}/invitations?cursor={$cursor}", HouseholdScenario::as($owner))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.next_cursor', null);
});

it('REQ-2.1 refuses a member with 403', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    getJson("/api/v1/households/{$household->id}/invitations", HouseholdScenario::as($member))
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'household.forbidden']]);
});

it('REQ-8.2 answers 404 for another household', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());

    getJson("/api/v1/households/{$household->id}/invitations", HouseholdScenario::as(User::factory()->create()))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'household.not_found']]);
});

it('REQ-8.1 answers 401 without a session or token', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());

    getJson("/api/v1/households/{$household->id}/invitations")->assertStatus(401);
});
