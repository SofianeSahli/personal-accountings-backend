<?php

declare(strict_types=1);

namespace App\Modules\Ledger\database\factories;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Frequency;
use App\Modules\Ledger\Models\RecurringRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringRule>
 */
final class RecurringRuleFactory extends Factory
{
    protected $model = RecurringRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = now('UTC')->addMonth()->toDateString();

        return [
            'book_id' => Book::factory(),
            'category_id' => fn (): string => CategoryFactory::newLeaf()->id,
            'amount' => fake()->numberBetween(1, 500_000),
            'other_name' => null,
            'comment' => null,
            'frequency' => Frequency::Monthly,
            'interval' => 1,
            'starts_on' => $startsOn,
            'ends_on' => null,
            'next_due_on' => $startsOn,
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

    public function startingOn(string $date, Frequency $frequency = Frequency::Monthly, int $interval = 1): static
    {
        return $this->state(fn (): array => [
            'starts_on' => $date,
            'next_due_on' => $date,
            'frequency' => $frequency,
            'interval' => $interval,
        ]);
    }
}
