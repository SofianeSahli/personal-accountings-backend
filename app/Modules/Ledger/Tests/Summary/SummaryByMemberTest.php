<?php

declare(strict_types=1);

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Tests\Support\LedgerScenario;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    LedgerScenario::categories();
});

it('REQ-3.1 returns each member\'s totals in a household book, largest expense first', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();
    LedgerScenario::spend($book, LedgerScenario::SALARY, 2_500_000, '2026-11-01', $owner);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 100_000, '2026-11-02', $owner);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 300_000, '2026-11-03', $member);
    LedgerScenario::spend($book, LedgerScenario::EMERGENCY_FUND, 50_000, '2026-11-04', $member);

    getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), HouseholdScenario::as($owner))
        ->assertOk()
        ->assertJsonPath('data.members', [
            [
                'account' => ['id' => $member->id, 'name' => 'Amira'],
                'income' => 0, 'expense' => 300_000, 'saving' => 50_000, 'balance' => -350_000, 'entries' => 2,
            ],
            [
                'account' => ['id' => $owner->id, 'name' => 'Sofiane'],
                'income' => 2_500_000, 'expense' => 100_000, 'saving' => 0, 'balance' => 2_400_000, 'entries' => 2,
            ],
        ]);
});

it('REQ-3.1 orders equal expenses by account id', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-01', $owner);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-01', $member);
    $ids = [$owner->id, $member->id];
    sort($ids);

    $members = getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), HouseholdScenario::as($member))
        ->assertOk()
        ->json('data.members');

    expect(array_column(array_column($members, 'account'), 'id'))->toBe($ids);
});

it('REQ-3.1 gives one line in a personal book', function (): void {
    $user = User::factory()->create(['name' => 'Sofiane']);
    $book = LedgerScenario::personalBook($user);
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 1_000, '2026-11-01', $user);
    LedgerScenario::spend($book, LedgerScenario::RESTAURANTS, 2_000, '2026-11-02', $user);

    getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), HouseholdScenario::as($user))
        ->assertOk()
        ->assertJsonCount(1, 'data.members')
        ->assertJsonPath('data.members.0.account', ['id' => $user->id, 'name' => 'Sofiane'])
        ->assertJsonPath('data.members.0.expense', 3_000);
});

it('REQ-3.1 counts entries made by a recurring rule for the rule\'s author', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();
    $rule = RecurringRule::factory()->inBook($book)->by($member)->create();
    Transaction::factory()->inBook($book)->by($member)
        ->in(LedgerScenario::category(LedgerScenario::SUPERMARKET))
        ->create(['amount' => 850_000, 'date' => '2026-11-05', 'recurring_rule_id' => $rule->id, 'rule_due_on' => '2026-11-05']);

    getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), HouseholdScenario::as($owner))
        ->assertOk()
        ->assertJsonPath('data.members.0.account.id', $member->id)
        ->assertJsonPath('data.members.0.expense', 850_000);
});

it('REQ-3.2 keeps the line and the name of a member who has left', function (): void {
    [$owner, $member, $book] = LedgerScenario::householdBook();
    LedgerScenario::spend($book, LedgerScenario::SUPERMARKET, 300_000, '2026-11-03', $member);
    HouseholdScenario::leave($member);

    getJson(LedgerScenario::summaryUrl($book, 'month=2026-11'), HouseholdScenario::as($owner))
        ->assertOk()
        ->assertJsonPath('data.members.0.account', ['id' => $member->id, 'name' => 'Amira'])
        ->assertJsonPath('data.members.0.expense', 300_000);
});
