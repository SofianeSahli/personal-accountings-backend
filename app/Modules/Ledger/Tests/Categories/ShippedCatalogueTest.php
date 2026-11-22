<?php

declare(strict_types=1);

use App\Modules\Ledger\Commands\SyncCategoriesCommand;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Support\CatalogueEntry;
use App\Modules\Ledger\Support\CategoryCatalogue;

use function Pest\Laravel\artisan;

const APPENDIX_A_PATH = 'specs/005-categories/requirements.md';

const TRANSLATIONS_PATH = 'specs/005-categories/translations';

const APPENDIX_A_COLUMNS = ['en' => 2, 'fr' => 3, 'ar' => 4];

/**
 * @return list<list<string>>
 */
function appendixARows(): array
{
    $contents = (string) file_get_contents(base_path(APPENDIX_A_PATH));
    $appendix = explode('## Appendix A', $contents, 2)[1];
    $rows = [];

    foreach (explode("\n", $appendix) as $line) {
        if (str_starts_with($line, '| `')) {
            $rows[] = array_map(trim(...), explode('|', trim(trim($line), '|')));
        }
    }

    return $rows;
}

/**
 * @return list<array{string, ?string, string}>
 */
function appendixACategories(): array
{
    $categories = [];
    $type = '';

    foreach (appendixARows() as $row) {
        $type = $row[5] === '' ? $type : $row[5];
        $categories[] = [trim($row[0], '`'), $row[1] === '' ? null : $row[1], $type];
    }

    return $categories;
}

function shippedCatalogue(): CategoryCatalogue
{
    return CategoryCatalogue::fromArray(require SyncCategoriesCommand::SHIPPED_CATALOGUE);
}

/**
 * @param  array<array-key, mixed>  $tree
 * @return array<string, mixed>
 */
function flattenTranslations(array $tree, string $prefix = ''): array
{
    $flat = [];

    foreach ($tree as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        $flat = is_array($value) ? [...$flat, ...flattenTranslations($value, $path)] : [...$flat, $path => $value];
    }

    return $flat;
}

it('REQ-2.1 REQ-2.8 ships a valid catalogue equal to Appendix A, in the same order', function (): void {
    $shipped = array_map(
        fn (CatalogueEntry $entry): array => [$entry->code, $entry->emoji, $entry->type->value],
        shippedCatalogue()->entries(),
    );

    expect($shipped)->toBe(appendixACategories())
        ->and(count($shipped))->toBe(170);
});

it('REQ-2.8 ships no names in the catalogue', function (): void {
    $contents = (string) file_get_contents(SyncCategoriesCommand::SHIPPED_CATALOGUE);

    expect($contents)->not->toContain("'name'")
        ->and($contents)->not->toContain("'label'")
        ->and($contents)->not->toContain('Supermarket');
});

it('REQ-2.7 syncs the shipped catalogue on an empty database', function (): void {
    artisan('ledger:sync-categories')
        ->expectsOutput('Categories: 170 created, 0 updated, 0 retired, 0 restored, 0 unchanged.')
        ->assertSuccessful();

    expect(Category::query()->whereNull('parent_id')->count())->toBe(11)
        ->and(Category::query()->where('is_other', true)->count())->toBe(33);
});

it('REQ-2.10 hands the frontend exactly one name per category in each language, as in Appendix A', function (string $language): void {
    $file = base_path(TRANSLATIONS_PATH."/{$language}.json");
    $translations = flattenTranslations((array) json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR));

    $expected = [];

    foreach (appendixARows() as $row) {
        $expected[Category::translationKeyOf(trim($row[0], '`'))] = trim($row[APPENDIX_A_COLUMNS[$language]], '*');
    }

    expect($translations)->toBe($expected);
})->with(array_keys(APPENDIX_A_COLUMNS));
