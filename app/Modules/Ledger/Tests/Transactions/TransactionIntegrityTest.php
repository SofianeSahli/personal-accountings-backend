<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\RecurringRule;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Tests\Support\LedgerScenario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('REQ-6.1 and REQ-6.2 give entries and rules a book, bigint amounts and plain dates', function (): void {
    $columns = fn (string $table): array => collect(DB::select("SHOW COLUMNS FROM `{$table}`"))
        ->mapWithKeys(fn (object $column): array => [$column->Field => strtolower($column->Type)])
        ->all();

    expect($columns('transactions'))->toMatchArray([
        'book_id' => 'char(36)',
        'amount' => 'bigint unsigned',
        'date' => 'date',
        'rule_due_on' => 'date',
        'deleted_at' => 'timestamp',
    ])->and($columns('recurring_rules'))->toMatchArray([
        'book_id' => 'char(36)',
        'amount' => 'bigint unsigned',
        'starts_on' => 'date',
        'next_due_on' => 'date',
        'deleted_at' => 'timestamp',
    ]);
});

it('REQ-6.3 never cascades from books, categories, users or rules to money rows', function (string $table, string $id): void {
    $rule = RecurringRule::factory()->create();
    $entry = Transaction::factory()->create(['recurring_rule_id' => $rule->id, 'rule_due_on' => '2026-11-01']);
    $ids = [
        'books' => $entry->book_id,
        'categories' => $entry->category_id,
        'users' => $entry->created_by,
        'recurring_rules' => $rule->id,
    ];

    expect(fn () => DB::table($table)->where('id', $ids[$id])->delete())->toThrow(QueryException::class);
    expect(Transaction::query()->whereKey($entry->id)->exists())->toBeTrue();
})->with([
    'book' => ['books', 'books'],
    'category' => ['categories', 'categories'],
    'user' => ['users', 'users'],
    'rule' => ['recurring_rules', 'recurring_rules'],
]);

it('REQ-6.3 refuses an unknown frequency at the database level', function (): void {
    expect(fn () => DB::table('recurring_rules')->where('id', RecurringRule::factory()->create()->id)->update(['frequency' => 'daily']))
        ->toThrow(QueryException::class);
});

it('REQ-6.3 soft-deletes entries and rules, keeping their rows', function (): void {
    $entry = Transaction::factory()->create();
    $rule = RecurringRule::factory()->create();

    $entry->delete();
    $rule->delete();

    expect(Transaction::withTrashed()->whereKey($entry->id)->exists())->toBeTrue()
        ->and(RecurringRule::withTrashed()->whereKey($rule->id)->exists())->toBeTrue();
});

it('REQ-6.2 keeps exact integer amounts at the largest accepted value', function (): void {
    $book = LedgerScenario::personalBook(User::factory()->create());
    $entry = Transaction::factory()->inBook($book)->create(['amount' => 999_999_999_999]);

    expect(Transaction::query()->findOrFail($entry->id)->amount)->toBe(999_999_999_999)
        ->and(Book::query()->count())->toBe(1)
        ->and(Category::query()->count())->toBeGreaterThan(0);
});
