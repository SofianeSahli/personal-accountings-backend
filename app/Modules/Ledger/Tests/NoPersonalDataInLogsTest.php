<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

it('REQ-9.4 writes no household, book or account name, nor email, to the logs', function (): void {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });
    $owner = User::factory()->create(['name' => 'Owner Secret-Name', 'email' => 'owner.private@example.com']);
    $member = User::factory()->create(['name' => 'Member Secret-Name', 'email' => 'member.private@example.com']);

    $householdId = (string) postJson('/api/v1/households', ['name' => 'Secret Household'], HouseholdScenario::as($owner))
        ->assertCreated()
        ->json('data.id');
    HouseholdScenario::join($householdId, $member);
    patchJson("/api/v1/households/{$householdId}", ['name' => 'Secret Renamed'], HouseholdScenario::as($owner))->assertOk();
    getJson("/api/v1/households/{$householdId}/members", HouseholdScenario::as($member))->assertOk();
    $bookId = (string) postJson('/api/v1/books', ['name' => 'Secret Book', 'household_id' => $householdId], HouseholdScenario::as($owner))
        ->assertCreated()
        ->json('data.id');
    postJson('/api/v1/books', ['name' => 'Secret Personal'], HouseholdScenario::as($member))->assertCreated();
    patchJson("/api/v1/books/{$bookId}", ['name' => 'Secret Book Renamed'], HouseholdScenario::as($member))->assertForbidden();
    patchJson("/api/v1/books/{$bookId}", ['name' => 'Secret Book Renamed'], HouseholdScenario::as($owner))->assertOk();
    getJson('/api/v1/books', HouseholdScenario::as($member))->assertOk();
    deleteJson("/api/v1/households/{$householdId}", [], HouseholdScenario::as($owner))->assertNoContent();

    foreach ($logged as $line) {
        expect($line)->not->toContain('Secret')
            ->and($line)->not->toContain('private@example.com');
    }
});

it('REQ-6.4 of spec 008 writes no amount or category of a plan to the logs', function (): void {
    BudgetScenario::setUp();
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });
    [, $member, $book] = LedgerScenario::householdBook();
    $headers = HouseholdScenario::as($member);

    putJson(BudgetScenario::url($book, '2026-10'), [
        'expected_income' => 987_654_321,
        'planned_saving' => 123_456_789,
        'lines' => [['category' => 'transport.fuel', 'planned' => 555_444_333]],
    ], $headers)->assertOk();
    putJson(BudgetScenario::url($book, '2026-10'), ['lines' => [['category' => 'nope', 'planned' => 1]]], $headers)->assertStatus(422);
    postJson(BudgetScenario::url($book, '2026-11', '/copy'), [], $headers)->assertOk();
    postJson(BudgetScenario::url($book, '2026-11', '/copy'), [], $headers)->assertStatus(409);
    getJson(BudgetScenario::url($book, '2026-11'), $headers)->assertOk();

    foreach ($logged as $line) {
        expect($line)->not->toContain('987654321')
            ->and($line)->not->toContain('123456789')
            ->and($line)->not->toContain('555444333')
            ->and($line)->not->toContain('transport')
            ->and($line)->not->toContain('nope');
    }
});
