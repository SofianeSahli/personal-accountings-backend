<?php

declare(strict_types=1);

use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Tests\Support\CategoryScenario;

use function Pest\Laravel\artisan;

it('REQ-2.2 syncs a catalogue file and prints what changed', function (): void {
    $path = CategoryScenario::fileOf(CategoryScenario::catalogue());

    artisan('ledger:sync-categories', ['--file' => $path])
        ->expectsOutput('Categories: 8 created, 0 updated, 0 retired, 0 restored, 0 unchanged.')
        ->assertSuccessful();

    artisan('ledger:sync-categories', ['--file' => $path])
        ->expectsOutput('Categories: 0 created, 0 updated, 0 retired, 0 restored, 8 unchanged.')
        ->assertSuccessful();

    expect(Category::query()->count())->toBe(8);
});

it('REQ-2.4 lists every problem of an invalid file, changes nothing and fails', function (): void {
    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['type'] = 'gift';
    array_pop($catalogue[1]['children'][0]['children']);

    artisan('ledger:sync-categories', ['--file' => CategoryScenario::fileOf($catalogue)])
        ->expectsOutput('The category catalogue is invalid. Nothing was changed.')
        ->expectsOutput('  - food: type must be expense, income or saving')
        ->expectsOutput('  - income.work: needs an "other" leaf')
        ->assertFailed();

    expect(Category::query()->count())->toBe(0);
});

it('REQ-2.5 fails on a type change and changes nothing', function (): void {
    CategoryScenario::sync(CategoryScenario::catalogue());
    $catalogue = CategoryScenario::catalogue();
    $catalogue[0]['type'] = 'saving';
    $catalogue[1]['emoji'] = '💰';

    artisan('ledger:sync-categories', ['--file' => CategoryScenario::fileOf($catalogue)])
        ->expectsOutput('  - food: type cannot change from expense to saving; add a new code instead')
        ->assertFailed();

    expect(Category::query()->where('code', 'income')->value('emoji'))->toBe('💼');
});

it('REQ-2.4 fails on a missing file or a file that returns no array', function (): void {
    $missing = sys_get_temp_dir().'/no-such-catalogue.php';
    $notAnArray = CategoryScenario::writeFile("<?php\n\ndeclare(strict_types=1);\n\nreturn 'food';\n");

    artisan('ledger:sync-categories', ['--file' => $missing])
        ->expectsOutput("  - {$missing}: file not found")
        ->assertFailed();

    artisan('ledger:sync-categories', ['--file' => $notAnArray])
        ->expectsOutput("  - {$notAnArray}: must return an array of categories")
        ->assertFailed();
});
