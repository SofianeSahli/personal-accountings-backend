<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('REQ-6.3 of spec 004 ends book access at once when a member leaves', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $member = HouseholdScenario::memberOf($household);
    $book = Book::factory()->ownedByHousehold($household->id)->create();
    getJson("/api/v1/books/{$book->id}", HouseholdScenario::as($member))->assertOk();

    postJson("/api/v1/households/{$household->id}/leave", [], HouseholdScenario::as($member))->assertNoContent();

    getJson("/api/v1/books/{$book->id}", HouseholdScenario::as($member))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'book.not_found']]);
    getJson('/api/v1/books', HouseholdScenario::as($member))->assertJsonPath('data', []);
});

it('REQ-6.3 of spec 004 ends book access at once when the owner removes a member', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $member = HouseholdScenario::memberOf($household);
    $book = Book::factory()->ownedByHousehold($household->id)->create();

    deleteJson("/api/v1/households/{$household->id}/members/{$member->id}", [], HouseholdScenario::as($owner))->assertNoContent();

    getJson("/api/v1/books/{$book->id}", HouseholdScenario::as($member))->assertStatus(404);
    getJson('/api/v1/books', HouseholdScenario::as($member))->assertJsonPath('data', []);
    getJson("/api/v1/books/{$book->id}", HouseholdScenario::as($owner))->assertOk();
});

it('REQ-4.7 of spec 004 gives a new member access to the household book', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $book = Book::factory()->ownedByHousehold($household->id)->create();
    $invitee = User::factory()->create(['email' => 'amira@example.com']);
    $invitation = HouseholdScenario::invite($household, 'amira@example.com');

    postJson("/api/v1/invitations/{$invitation->id}/accept", [], HouseholdScenario::as($invitee))->assertOk();

    getJson("/api/v1/books/{$book->id}", HouseholdScenario::as($invitee))
        ->assertOk()
        ->assertJsonPath('data.can_manage', false);
});
