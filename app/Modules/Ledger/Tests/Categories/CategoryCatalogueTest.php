<?php

declare(strict_types=1);

use App\Modules\Ledger\Models\CategoryLevel;
use App\Modules\Ledger\Models\CategoryType;
use App\Modules\Ledger\Support\CatalogueEntry;
use App\Modules\Ledger\Support\CategoryCatalogue;
use App\Modules\Ledger\Tests\Support\CategoryScenario;

it('REQ-2.1 reads a valid catalogue in file order, with levels, types and "Other" leaves', function (): void {
    $entries = CategoryCatalogue::fromArray(CategoryScenario::catalogue())->entries();

    expect(array_map(fn (CatalogueEntry $entry): array => [
        $entry->code, $entry->parentCode, $entry->level, $entry->type, $entry->emoji, $entry->position, $entry->isOther,
    ], $entries))->toBe([
        ['food', null, CategoryLevel::Top, CategoryType::Expense, '🛒', 0, false],
        ['food.groceries', 'food', CategoryLevel::Group, CategoryType::Expense, '🏪', 0, false],
        ['food.groceries.supermarket', 'food.groceries', CategoryLevel::Leaf, CategoryType::Expense, '🥫', 0, false],
        ['food.groceries.other', 'food.groceries', CategoryLevel::Leaf, CategoryType::Expense, '📝', 1, true],
        ['income', null, CategoryLevel::Top, CategoryType::Income, '💼', 1, false],
        ['income.work', 'income', CategoryLevel::Group, CategoryType::Income, '💼', 0, false],
        ['income.work.salary', 'income.work', CategoryLevel::Leaf, CategoryType::Income, null, 0, false],
        ['income.work.other', 'income.work', CategoryLevel::Leaf, CategoryType::Income, '📝', 1, true],
    ]);
});

it('REQ-2.4 refuses a catalogue that is not a non-empty list', function (mixed $nodes): void {
    expect(CategoryScenario::problemsOf($nodes))->toBe(['catalogue: needs a non-empty list of categories']);
})->with([
    'empty' => [[]],
    'not a list' => [['food' => []]],
    'not an array' => ['food'],
]);

it('REQ-2.4 refuses a category without a string code', function (): void {
    $catalogue = CategoryScenario::catalogue();
    unset($catalogue[0]['children'][0]['children'][0]['code']);

    expect(CategoryScenario::problemsOf($catalogue))->toBe(['food.groceries, item 0: needs a string code']);
});

it('REQ-2.4 refuses a code segment that is not lower snake case or is "label"', function (string $segment): void {
    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['children'][0]['children'][0]['code'] = 'food.groceries.'.$segment;

    expect(CategoryScenario::problemsOf($catalogue))->toBe([
        "food.groceries.{$segment}: segment \"{$segment}\" must match /^[a-z][a-z0-9_]*$/ and must not be \"label\"",
    ]);
})->with(['Bakery', 'bakery-shop', '1bakery', 'label', '']);

it('REQ-2.4 refuses a category that is not at its level', function (): void {
    $catalogue = CategoryScenario::catalogue();
    $catalogue[] = ['code' => 'drinks.water', 'type' => 'expense', 'emoji' => '💧', 'children' => []];

    expect(CategoryScenario::problemsOf($catalogue))
        ->toBe(['drinks.water: must have 1 segment(s) to sit at level 1 of 3 (top, group, leaf)']);
});

it('REQ-2.4 refuses a fourth level', function (): void {
    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['children'][0]['children'][0]['children'] = [['code' => 'food.groceries.supermarket.bio', 'emoji' => '🥬']];

    expect(CategoryScenario::problemsOf($catalogue))
        ->toBe(['food.groceries.supermarket: a leaf category cannot have "children"']);
});

it('REQ-2.4 refuses a child whose code does not start with its parent\'s code', function (): void {
    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['children'][0]['children'][0]['code'] = 'drinks.groceries.water';

    expect(CategoryScenario::problemsOf($catalogue))
        ->toBe(['drinks.groceries.water: must start with its parent\'s code "food.groceries."']);
});

it('REQ-2.4 refuses a code that appears twice', function (): void {
    $catalogue = CategoryScenario::catalogue();
    array_unshift($catalogue[0]['children'][0]['children'], ['code' => 'food.groceries.supermarket', 'emoji' => '🥫']);

    expect(CategoryScenario::problemsOf($catalogue))->toBe(['food.groceries.supermarket: appears more than once']);
});

it('REQ-2.4 refuses a top category or a group with no children', function (): void {
    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['children'][0]['children'] = [];
    unset($catalogue[1]['children']);

    expect(CategoryScenario::problemsOf($catalogue))->toBe([
        'food.groceries: needs a non-empty list of categories',
        'income: needs a non-empty list of categories',
    ]);
});

it('REQ-2.4 refuses a top category without a valid type', function (mixed $type): void {
    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['type'] = $type;

    expect(CategoryScenario::problemsOf($catalogue))->toBe(['food: type must be expense, income or saving']);
})->with(['gift', null, 1, 'Expense']);

it('REQ-2.4 refuses a type on a group or a leaf', function (): void {
    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['children'][0]['type'] = 'income';
    $catalogue[0]['children'][0]['children'][0]['type'] = 'income';

    expect(CategoryScenario::problemsOf($catalogue))->toBe([
        'food.groceries: a group category cannot have "type"',
        'food.groceries.supermarket: a leaf category cannot have "type"',
    ]);
});

it('REQ-2.4 refuses a top category or a group without an emoji', function (): void {
    $catalogue = CategoryScenario::catalogue();
    unset($catalogue[0]['emoji']);
    $catalogue[1]['children'][0]['emoji'] = '';

    expect(CategoryScenario::problemsOf($catalogue))->toBe(['food: needs an emoji', 'income.work: needs an emoji']);
});

it('REQ-2.4 accepts a leaf without an emoji but refuses an empty one', function (): void {
    $catalogue = CategoryScenario::catalogue();
    unset($catalogue[0]['children'][0]['children'][0]['emoji']);
    $catalogue[1]['children'][0]['children'][0]['emoji'] = '';

    expect(CategoryScenario::problemsOf($catalogue))->toBe(['income.work.salary: emoji must be a non-empty string or null']);
});

it('REQ-2.4 refuses a group without an "other" leaf', function (): void {
    $catalogue = CategoryScenario::catalogue();
    array_pop($catalogue[0]['children'][0]['children']);

    expect(CategoryScenario::problemsOf($catalogue))->toBe(['food.groceries: needs an "other" leaf']);
});

it('REQ-2.4 refuses a group whose "other" leaf is not the last one', function (): void {
    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['children'][0]['children'] = array_reverse($catalogue[0]['children'][0]['children']);

    expect(CategoryScenario::problemsOf($catalogue))->toBe(['food.groceries: its "other" leaf must be the last one']);
});

it('REQ-2.4 refuses unknown keys', function (): void {
    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['name'] = 'Food';

    expect(CategoryScenario::problemsOf($catalogue))->toBe(['food: a top category cannot have "name"']);
});

it('REQ-2.4 lists every problem at once', function (): void {
    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['type'] = 'gift';
    unset($catalogue[0]['children'][0]['emoji']);
    array_pop($catalogue[1]['children'][0]['children']);

    expect(CategoryScenario::problemsOf($catalogue))->toBe([
        'food: type must be expense, income or saving',
        'food.groceries: needs an emoji',
        'income.work: needs an "other" leaf',
    ]);
});
