<?php

declare(strict_types=1);

use App\Modules\Household\Events\HouseholdDeleted;
use App\Modules\Household\Models\Household;
use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('REQ-2.9 lets the owner delete the household and its memberships (token mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    HouseholdScenario::memberOf($household);

    deleteJson("/api/v1/households/{$household->id}", [], HouseholdScenario::as($owner))
        ->assertNoContent();

    expect(Household::query()->count())->toBe(0)
        ->and(HouseholdMember::query()->count())->toBe(0);
});

it('REQ-2.9 lets the owner delete the household (cookie mode)', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    WebBrowser::signedInAs($owner)->deleteJson("/api/v1/households/{$household->id}")->assertNoContent();

    expect(Household::query()->count())->toBe(0);
});

it('REQ-2.12 keeps the household and its memberships, soft-deleted', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    HouseholdScenario::memberOf($household);

    deleteJson("/api/v1/households/{$household->id}", [], HouseholdScenario::as($owner))->assertNoContent();

    expect(Household::withTrashed()->whereNotNull('deleted_at')->count())->toBe(1)
        ->and(HouseholdMember::withTrashed()->whereNotNull('deleted_at')->count())->toBe(2);
});

it('REQ-2.9 refuses a member and changes nothing', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);

    deleteJson("/api/v1/households/{$household->id}", [], HouseholdScenario::as($member))
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'household.forbidden']]);

    expect(Household::query()->count())->toBe(1)
        ->and(HouseholdMember::query()->count())->toBe(2);
});

it('REQ-2.10 frees every former member to create a household, and hides the deleted one', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $member = HouseholdScenario::memberOf($household);

    deleteJson("/api/v1/households/{$household->id}", [], HouseholdScenario::as($owner))->assertNoContent();

    getJson("/api/v1/households/{$household->id}", HouseholdScenario::as($owner))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'household.not_found']]);
    deleteJson("/api/v1/households/{$household->id}", [], HouseholdScenario::as($owner))->assertStatus(404);
    getJson('/api/v1/households', HouseholdScenario::as($member))->assertOk()->assertJsonPath('data', []);
    postJson('/api/v1/households', ['name' => 'New start'], HouseholdScenario::as($owner))->assertCreated();
    postJson('/api/v1/households', ['name' => 'Mine'], HouseholdScenario::as($member))->assertCreated();
});

it('REQ-2.11 announces the deletion after commit with the household id', function (): void {
    Event::fake([HouseholdDeleted::class]);
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);

    deleteJson("/api/v1/households/{$household->id}", [], HouseholdScenario::as($owner))->assertNoContent();

    Event::assertDispatched(HouseholdDeleted::class, fn (HouseholdDeleted $event): bool => $event->householdId === $household->id);
});
