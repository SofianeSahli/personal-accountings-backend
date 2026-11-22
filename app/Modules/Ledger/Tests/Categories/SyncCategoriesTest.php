<?php

declare(strict_types=1);

use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\CategoryLevel;
use App\Modules\Ledger\Models\CategoryType;
use App\Modules\Ledger\Support\InvalidCatalogue;
use App\Modules\Ledger\Tests\Support\CategoryScenario;
use Illuminate\Support\Carbon;

function categoryByCode(string $code): Category
{
    return Category::query()->where('code', $code)->firstOrFail();
}

it('REQ-2.2 REQ-2.9 creates every category of a new catalogue', function (): void {
    $report = CategoryScenario::sync(CategoryScenario::catalogue());

    expect([$report->created, $report->updated, $report->retired, $report->restored, $report->unchanged])
        ->toBe([8, 0, 0, 0, 0]);

    $food = categoryByCode('food');
    $groceries = categoryByCode('food.groceries');
    $other = categoryByCode('food.groceries.other');
    $salary = categoryByCode('income.work.salary');

    expect($food->parent_id)->toBeNull()
        ->and($food->level)->toBe(CategoryLevel::Top)
        ->and($food->translation_key)->toBe('categories.food.label')
        ->and($groceries->parent_id)->toBe($food->id)
        ->and($groceries->level)->toBe(CategoryLevel::Group)
        ->and($groceries->type)->toBe(CategoryType::Expense)
        ->and($other->parent_id)->toBe($groceries->id)
        ->and($other->translation_key)->toBe('categories.food.groceries.other.label')
        ->and($other->is_other)->toBeTrue()
        ->and($other->position)->toBe(1)
        ->and($other->emoji)->toBe('📝')
        ->and($salary->type)->toBe(CategoryType::Income)
        ->and($salary->emoji)->toBeNull()
        ->and($salary->is_other)->toBeFalse()
        ->and($salary->retired_at)->toBeNull();
});

it('REQ-2.3 changes nothing when the same catalogue is synced twice', function (): void {
    Carbon::setTestNow('2026-11-22 10:00:00');
    CategoryScenario::sync(CategoryScenario::catalogue());
    $before = Category::query()->orderBy('code')->get()->toArray();

    Carbon::setTestNow('2026-11-23 10:00:00');
    $report = CategoryScenario::sync(CategoryScenario::catalogue());
    Carbon::setTestNow();

    expect([$report->created, $report->updated, $report->retired, $report->restored, $report->unchanged])
        ->toBe([0, 0, 0, 0, 8])
        ->and(Category::query()->orderBy('code')->get()->toArray())->toBe($before);
});

it('REQ-2.2 updates the emoji and order of existing categories, never their translation key', function (): void {
    CategoryScenario::sync(CategoryScenario::catalogue());
    $id = categoryByCode('income')->id;

    $catalogue = CategoryScenario::catalogue();
    $catalogue = [$catalogue[1], $catalogue[0]];
    $catalogue[0]['emoji'] = '💰';
    $report = CategoryScenario::sync($catalogue);

    $income = categoryByCode('income');

    expect([$report->created, $report->updated, $report->unchanged])->toBe([0, 2, 6])
        ->and($income->id)->toBe($id)
        ->and($income->emoji)->toBe('💰')
        ->and($income->position)->toBe(0)
        ->and(categoryByCode('food')->position)->toBe(1)
        ->and($income->translation_key)->toBe('categories.income.label');
});

it('REQ-2.2 REQ-2.6 retires categories that left the catalogue and keeps their rows', function (): void {
    CategoryScenario::sync(CategoryScenario::catalogue());

    $catalogue = CategoryScenario::catalogue();
    array_shift($catalogue[0]['children'][0]['children']);
    unset($catalogue[1]);
    $report = CategoryScenario::sync(array_values($catalogue));

    expect([$report->retired, $report->updated, $report->unchanged])->toBe([5, 1, 2])
        ->and(Category::query()->count())->toBe(8)
        ->and(categoryByCode('food.groceries.supermarket')->isRetired())->toBeTrue()
        ->and(categoryByCode('income')->isRetired())->toBeTrue()
        ->and(categoryByCode('income.work.salary')->isRetired())->toBeTrue()
        ->and(categoryByCode('food.groceries.other')->isRetired())->toBeFalse();
});

it('REQ-2.2 restores retired categories that come back', function (): void {
    CategoryScenario::sync(CategoryScenario::catalogue());
    $catalogue = CategoryScenario::catalogue();
    unset($catalogue[1]);
    CategoryScenario::sync(array_values($catalogue));
    $id = categoryByCode('income.work.salary')->id;

    $report = CategoryScenario::sync(CategoryScenario::catalogue());

    expect([$report->created, $report->restored, $report->retired, $report->unchanged])->toBe([0, 4, 0, 4])
        ->and(categoryByCode('income.work.salary')->id)->toBe($id)
        ->and(categoryByCode('income.work.salary')->isRetired())->toBeFalse()
        ->and(categoryByCode('income')->isRetired())->toBeFalse();
});

it('REQ-2.5 refuses to change the type of an existing code and writes nothing', function (): void {
    CategoryScenario::sync(CategoryScenario::catalogue());

    $catalogue = CategoryScenario::catalogue();
    $catalogue[1]['type'] = 'saving';
    $catalogue[0]['emoji'] = '🥐';

    $problems = [];

    try {
        CategoryScenario::sync($catalogue);
    } catch (InvalidCatalogue $invalid) {
        $problems = $invalid->problems;
    }

    expect($problems)->toBe([
        'income: type cannot change from income to saving; add a new code instead',
        'income.work: type cannot change from income to saving; add a new code instead',
        'income.work.salary: type cannot change from income to saving; add a new code instead',
        'income.work.other: type cannot change from income to saving; add a new code instead',
    ])
        ->and(categoryByCode('food')->emoji)->toBe('🛒')
        ->and(categoryByCode('income.work.salary')->type)->toBe(CategoryType::Income);
});

it('REQ-2.5 refuses to give a retired code another type', function (): void {
    Category::factory()->code('savings')->retired()->create(['type' => CategoryType::Saving]);

    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['code'] = 'savings';
    $catalogue[0]['children'] = [[
        'code' => 'savings.goal',
        'emoji' => '🎯',
        'children' => [['code' => 'savings.goal.other', 'emoji' => '📝']],
    ]];

    expect(fn () => CategoryScenario::sync($catalogue))->toThrow(InvalidCatalogue::class)
        ->and(Category::query()->count())->toBe(1);
});
