<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Tests\Support;

use App\Modules\Household\Tests\Support\HouseholdScenario;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Models\BudgetPlan;
use App\Modules\Ledger\Models\BudgetPlanLine;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\travelTo;

final class BudgetScenario
{
    public const TODAY = '2026-11-25 10:00:00';

    public const PETROL = 'transport.fuel.petrol';

    public const MEDICINE = 'health.pharmacy.medicine';

    public static function setUp(): void
    {
        travelTo(CarbonImmutable::parse(self::TODAY, 'UTC'));
        LedgerScenario::categories();
        LedgerScenario::leaf(self::PETROL);
        LedgerScenario::leaf(self::MEDICINE);
    }

    public static function url(Book $book, string $month, string $suffix = ''): string
    {
        return "/api/v1/books/{$book->id}/budgets/{$month}".$suffix;
    }

    /**
     * @param  array<string, int>  $lines
     */
    public static function plan(Book $book, string $month, array $lines = [], ?int $income = null, ?int $saving = null): BudgetPlan
    {
        $plan = BudgetPlan::factory()->inBook($book)->forMonth($month)->create([
            'expected_income' => $income,
            'planned_saving' => $saving,
        ]);

        foreach ($lines as $code => $planned) {
            BudgetPlanLine::factory()->inPlan($plan)->on(LedgerScenario::category($code), $planned)->create();
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(User $user, Book $book, string $month): array
    {
        return (array) getJson(self::url($book, $month), HouseholdScenario::as($user))->assertOk()->json('data');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    public static function save(User $user, Book $book, string $month, array $body): TestResponse
    {
        return putJson(self::url($book, $month), $body, HouseholdScenario::as($user));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    public static function copy(User $user, Book $book, string $month, array $body = []): TestResponse
    {
        return postJson(self::url($book, $month, '/copy'), $body, HouseholdScenario::as($user));
    }

    /**
     * @return list<array{category: string, planned: int}>
     */
    public static function linesOf(Book $book, string $month): array
    {
        $plan = BudgetPlan::query()->where('book_id', $book->id)->where('month', $month.'-01')->with('lines.category')->first();

        if ($plan === null) {
            return [];
        }

        $lines = $plan->lines
            ->map(fn (BudgetPlanLine $line): array => ['category' => $line->category->code, 'planned' => $line->planned])
            ->sortBy('category')
            ->values()
            ->all();

        return array_values($lines);
    }
}
