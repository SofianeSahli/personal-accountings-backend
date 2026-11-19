<?php

declare(strict_types=1);

use App\Modules\Household\Models\HouseholdMember;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Tests\Support\WebBrowser;
use Illuminate\Support\Carbon;

use function Pest\Laravel\getJson;

it('REQ-7.1 lists owners first, then members by date joined (token mode)', function (): void {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $owner = User::factory()->create(['name' => 'Sofiane']);
    $household = HouseholdScenario::ownedBy($owner);
    Carbon::setTestNow('2026-11-21 10:00:00');
    $first = User::factory()->create(['name' => 'Amira']);
    HouseholdScenario::join($household, $first);
    Carbon::setTestNow('2026-11-22 10:00:00');
    $second = User::factory()->create(['name' => 'Yassine']);
    HouseholdScenario::join($household, $second);
    Carbon::setTestNow();

    getJson("/api/v1/households/{$household->id}/members", HouseholdScenario::as($second))
        ->assertOk()
        ->assertJsonPath('data', [
            ['id' => $owner->id, 'name' => 'Sofiane', 'role' => 'owner'],
            ['id' => $first->id, 'name' => 'Amira', 'role' => 'member'],
            ['id' => $second->id, 'name' => 'Yassine', 'role' => 'member'],
        ]);
});

it('REQ-7.2 never shows another field of an account (cookie mode)', function (): void {
    $owner = User::factory()->create(['phone' => '+21620123456']);
    $household = HouseholdScenario::ownedBy($owner);

    $response = WebBrowser::signedInAs($owner)->getJson("/api/v1/households/{$household->id}/members");

    $response->assertOk();
    expect(array_keys((array) $response->json('data.0')))->toBe(['id', 'name', 'role'])
        ->and($response->getContent())->not->toContain($owner->email)
        ->and($response->getContent())->not->toContain('+21620123456');
});

it('REQ-7.3 shows the current account name', function (): void {
    $owner = User::factory()->create(['name' => 'Old']);
    $household = HouseholdScenario::ownedBy($owner);
    $owner->update(['name' => 'New']);

    getJson("/api/v1/households/{$household->id}/members", HouseholdScenario::as($owner))
        ->assertJsonPath('data.0.name', 'New');
});

it('REQ-7.1 pages the list with a cursor', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    HouseholdMember::factory()->count(25)->create(['household_id' => $household->id]);
    $headers = HouseholdScenario::as($owner);

    $first = getJson("/api/v1/households/{$household->id}/members", $headers)->assertOk();
    $cursor = (string) $first->json('meta.next_cursor');
    $second = getJson("/api/v1/households/{$household->id}/members?cursor={$cursor}", $headers)->assertOk();

    expect($first->json('data'))->toHaveCount(25)
        ->and($first->json('data.0.id'))->toBe($owner->id)
        ->and($second->json('data'))->toHaveCount(1)
        ->and($second->json('meta.next_cursor'))->toBeNull();
    $ids = [...array_column((array) $first->json('data'), 'id'), ...array_column((array) $second->json('data'), 'id')];
    expect(array_unique($ids))->toHaveCount(26);
});
