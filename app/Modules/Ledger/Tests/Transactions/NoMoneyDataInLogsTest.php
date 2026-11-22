<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Carbon\CarbonImmutable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\artisan;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

it('REQ-6.6 writes no amount, Other name, comment or name to the logs', function (): void {
    LedgerScenario::categories();
    travelTo(CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC'));
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });
    [$owner, $member, $book] = LedgerScenario::householdBook();
    $owner->update(['name' => 'Owner Secret-Name']);
    $headers = HouseholdScenario::as($member);

    $entryId = postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry([
        'amount' => 987_654_321,
        'category' => LedgerScenario::GROCERIES_OTHER,
        'other_name' => 'Secret-Other',
        'comment' => 'Secret-Comment',
    ]), $headers)->assertCreated()->json('data.id');
    patchJson(LedgerScenario::transactionsUrl($book, '/'.$entryId), ['comment' => 'Secret-Comment-2'], $headers)->assertOk();
    getJson(LedgerScenario::transactionsUrl($book), $headers)->assertOk();
    $ruleId = postJson(LedgerScenario::rulesUrl($book), LedgerScenario::rule([
        'amount' => 123_456_789,
        'comment' => 'Secret-Rule',
        'starts_on' => '2026-11-01',
    ]), $headers)->assertCreated()->json('data.id');
    getJson("/api/v1/books/{$book->id}/other-names?category=".LedgerScenario::GROCERIES_OTHER, $headers)->assertOk();
    artisan('ledger:record-due-entries')->assertSuccessful();
    deleteJson(LedgerScenario::rulesUrl($book, '/'.$ruleId), [], $headers)->assertNoContent();
    deleteJson(LedgerScenario::transactionsUrl($book, '/'.$entryId), [], $headers)->assertNoContent();

    foreach ($logged as $line) {
        expect($line)->not->toContain('Secret')
            ->and($line)->not->toContain('987654321')
            ->and($line)->not->toContain('123456789')
            ->and($line)->not->toContain('Amira');
    }
});
