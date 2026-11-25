<?php

declare(strict_types=1);

namespace App\Modules\Ledger\database\factories;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Support\BudgetMonth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetPlan>
 */
final class BudgetPlanFactory extends Factory
{
    protected $model = BudgetPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'month' => now('UTC')->startOfMonth()->toDateString(),
            'expected_income' => null,
            'planned_saving' => null,
            'created_by' => User::factory(),
            'updated_by' => fn (array $attributes): mixed => $attributes['created_by'],
        ];
    }

    public function inBook(Book $book): static
    {
        return $this->state(fn (): array => ['book_id' => $book->id]);
    }

    public function forMonth(string $month): static
    {
        return $this->state(fn (): array => ['month' => BudgetMonth::parse($month)->first]);
    }
}
