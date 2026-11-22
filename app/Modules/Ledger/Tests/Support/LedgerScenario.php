<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Tests\Support;

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\CategoryLevel;
use App\Modules\Ledger\Models\CategoryType;

final class LedgerScenario
{
    public const SUPERMARKET = 'food.groceries.supermarket';

    public const GROCERIES_OTHER = 'food.groceries.other';

    public const RESTAURANTS = 'food.eating_out.restaurants';

    public const SALARY = 'income.work.salary';

    public const EMERGENCY_FUND = 'savings.reserves.emergency_fund';

    public static function categories(): void
    {
        self::leaf(self::SUPERMARKET);
        self::leaf(self::GROCERIES_OTHER);
        self::leaf(self::RESTAURANTS);
        self::leaf(self::SALARY, CategoryType::Income);
        self::leaf(self::EMERGENCY_FUND, CategoryType::Saving);
    }

    public static function leaf(string $code, CategoryType $type = CategoryType::Expense): Category
    {
        $segments = explode('.', $code);
        $parent = null;
        $current = '';

        foreach ($segments as $depth => $segment) {
            $current = $current === '' ? $segment : $current.'.'.$segment;
            $existing = Category::query()->where('code', $current)->first();

            if ($existing === null) {
                $factory = $parent === null
                    ? Category::factory()->code($current)->state(['type' => $type, 'level' => CategoryLevel::Top])
                    : Category::factory()->under($parent, $segment, $depth);
                $existing = $factory->create();
            }

            $parent = $existing;
        }

        return $parent;
    }

    public static function category(string $code): Category
    {
        return Category::query()->where('code', $code)->firstOrFail();
    }

    public static function personalBook(User $user, string $timezone = 'Africa/Tunis', string $currency = 'TND'): Book
    {
        return Book::factory()->ownedByAccount($user)->create(['timezone' => $timezone, 'currency' => $currency]);
    }

    /**
     * @return array{0: User, 1: User, 2: Book}
     */
    public static function householdBook(): array
    {
        $owner = User::factory()->create(['name' => 'Sofiane']);
        $household = HouseholdScenario::ownedBy($owner);
        $member = User::factory()->create(['name' => 'Amira']);
        HouseholdScenario::join($household, $member);

        return [$owner, $member, Book::factory()->ownedByHousehold($household->id)->create()];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function entry(array $overrides = []): array
    {
        return [
            'amount' => 12_500,
            'date' => now('Africa/Tunis')->toDateString(),
            'category' => self::SUPERMARKET,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function rule(array $overrides = []): array
    {
        return [
            'amount' => 850_000,
            'category' => self::SUPERMARKET,
            'frequency' => 'monthly',
            'starts_on' => now('Africa/Tunis')->addMonth()->toDateString(),
            ...$overrides,
        ];
    }

    public static function transactionsUrl(Book $book, string $suffix = ''): string
    {
        return "/api/v1/books/{$book->id}/transactions".$suffix;
    }

    public static function rulesUrl(Book $book, string $suffix = ''): string
    {
        return "/api/v1/books/{$book->id}/recurring-rules".$suffix;
    }
}
