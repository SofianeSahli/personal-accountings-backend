<?php

declare(strict_types=1);

namespace App\Modules\Ledger\database\factories;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
final class BookFactory extends Factory
{
    protected $model = Book::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Dépenses '.fake()->word(),
            'currency' => 'TND',
            'timezone' => 'Africa/Tunis',
            'owner_type' => Book::ACCOUNT_OWNER,
            'owner_id' => User::factory(),
        ];
    }

    public function ownedByAccount(User $user): static
    {
        return $this->state(fn (): array => ['owner_type' => Book::ACCOUNT_OWNER, 'owner_id' => $user->id]);
    }

    public function ownedByHousehold(string $householdId): static
    {
        return $this->state(fn (): array => ['owner_type' => Book::HOUSEHOLD_OWNER, 'owner_id' => $householdId]);
    }
}
