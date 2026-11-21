<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('REQ-8.8 lets the database refuse a book without an owner', function (array $columns): void {
    $row = [
        'id' => (string) Str::uuid(),
        'name' => 'Raw',
        'currency' => 'TND',
        'timezone' => 'Africa/Tunis',
        'created_at' => now(),
        'updated_at' => now(),
        ...$columns,
    ];

    expect(fn () => DB::table('books')->insert($row))->toThrow(QueryException::class);
})->with([
    'no owner type' => [['owner_id' => (string) Str::uuid()]],
    'no owner id' => [['owner_type' => 'account']],
    'unknown owner type' => [['owner_type' => 'company', 'owner_id' => (string) Str::uuid()]],
]);

it('REQ-2.3 lets the database refuse a second live book for one household', function (): void {
    $householdId = (string) Str::uuid();
    Book::factory()->ownedByHousehold($householdId)->create();

    expect(fn () => Book::factory()->ownedByHousehold($householdId)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('REQ-8.9 no longer counts a deleted household book', function (): void {
    $householdId = (string) Str::uuid();
    Book::factory()->ownedByHousehold($householdId)->create()->delete();

    Book::factory()->ownedByHousehold($householdId)->create();

    expect(Book::withTrashed()->where('owner_id', $householdId)->count())->toBe(2);
});

it('REQ-8.8 stores the owner type as its API word', function (): void {
    $user = User::factory()->create();

    $book = Book::factory()->ownedByAccount($user)->create();

    expect(DB::table('books')->where('id', $book->id)->value('owner_type'))->toBe('account')
        ->and($user->getMorphClass())->toBe('account');
});
