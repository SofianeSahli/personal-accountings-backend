<?php

declare(strict_types=1);

namespace App\Modules\Ledger\database\factories;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
final class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'category_id' => fn (): string => CategoryFactory::newLeaf()->id,
            'recurring_rule_id' => null,
            'rule_due_on' => null,
            'amount' => fake()->numberBetween(1, 500_000),
            'date' => now('UTC')->toDateString(),
            'other_name' => null,
            'comment' => null,
            'created_by' => User::factory(),
            'updated_by' => fn (array $attributes): mixed => $attributes['created_by'],
        ];
    }

    public function inBook(Book $book): static
    {
        return $this->state(fn (): array => ['book_id' => $book->id]);
    }

    public function by(User $user): static
    {
        return $this->state(fn (): array => ['created_by' => $user->id, 'updated_by' => $user->id]);
    }

    public function in(Category $category, ?string $otherName = null): static
    {
        return $this->state(fn (): array => ['category_id' => $category->id, 'other_name' => $otherName]);
    }
}
