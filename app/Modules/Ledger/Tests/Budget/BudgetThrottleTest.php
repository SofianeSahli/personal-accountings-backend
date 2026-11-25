<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Support\LedgerWriteLimit;
use App\Modules\Ledger\Tests\Support\BudgetScenario;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    BudgetScenario::setUp();
});

it('REQ-2.11 and REQ-5.6 count saves and copies with spec 006\'s writes, 60 a minute per account', function (): void {
    $user = User::factory()->create();
    $book = LedgerScenario::personalBook($user);
    BudgetScenario::plan($book, '2026-10', ['food' => 1_000]);
    $headers = HouseholdScenario::as($user);

    for ($i = 1; $i < LedgerWriteLimit::PER_MINUTE - 1; $i++) {
        postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(), $headers)->assertCreated();
    }

    BudgetScenario::save($user, $book, '2026-11', ['lines' => []])->assertOk();
    BudgetScenario::copy($user, $book, '2026-11')->assertOk();

    BudgetScenario::save($user, $book, '2026-11', ['lines' => []])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
    BudgetScenario::copy($user, $book, '2026-11', ['replace' => true])->assertStatus(429);
    postJson(LedgerScenario::transactionsUrl($book), LedgerScenario::entry(), $headers)->assertStatus(429);
    BudgetScenario::read($user, $book, '2026-11');

    $other = User::factory()->create();
    BudgetScenario::save($other, LedgerScenario::personalBook($other), '2026-11', ['lines' => []])->assertOk();
});
