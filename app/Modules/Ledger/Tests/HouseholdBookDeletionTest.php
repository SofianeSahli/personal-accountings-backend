<?php

declare(strict_types=1);

use App\Modules\Household\Events\HouseholdDeleted;
use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Actions\DeleteHouseholdBook;
use App\Modules\Ledger\Models\Book;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;

it('REQ-2.11 soft-deletes the household book when the household is deleted', function (): void {
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $member = HouseholdScenario::memberOf($household);
    $book = Book::factory()->ownedByHousehold($household->id)->create();
    $personal = Book::factory()->ownedByAccount($owner)->create();

    deleteJson("/api/v1/households/{$household->id}", [], HouseholdScenario::as($owner))->assertNoContent();

    expect(Book::query()->pluck('id')->all())->toBe([$personal->id])
        ->and(Book::withTrashed()->find($book->id)?->deleted_at)->not->toBeNull();
    getJson("/api/v1/books/{$book->id}", HouseholdScenario::as($owner))
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'book.not_found']]);
    getJson('/api/v1/books', HouseholdScenario::as($member))->assertJsonPath('data', []);
});

it('REQ-2.11 makes the book unreachable even before Ledger has reacted', function (): void {
    Event::fake([HouseholdDeleted::class]);
    $owner = User::factory()->create();
    $household = HouseholdScenario::ownedBy($owner);
    $book = Book::factory()->ownedByHousehold($household->id)->create();

    deleteJson("/api/v1/households/{$household->id}", [], HouseholdScenario::as($owner))->assertNoContent();

    expect(Book::query()->find($book->id))->not->toBeNull();
    getJson("/api/v1/books/{$book->id}", HouseholdScenario::as($owner))->assertStatus(404);
    getJson('/api/v1/books', HouseholdScenario::as($owner))->assertJsonPath('data', []);
});

it('REQ-2.11 deletes only that household\'s book, and does so idempotently', function (): void {
    $household = HouseholdScenario::ownedBy(User::factory()->create());
    $book = Book::factory()->ownedByHousehold($household->id)->create();
    $other = Book::factory()->ownedByHousehold(HouseholdScenario::ownedBy(User::factory()->create())->id)->create();

    app(DeleteHouseholdBook::class)->handle($household->id);
    app(DeleteHouseholdBook::class)->handle($household->id);

    expect(Book::query()->pluck('id')->all())->toBe([$other->id])
        ->and(Book::withTrashed()->count())->toBe(2)
        ->and($book->fresh()?->trashed())->toBeTrue();
});
