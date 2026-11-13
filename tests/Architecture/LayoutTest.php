<?php

declare(strict_types=1);

use Tests\Architecture\Support\ModuleMap;
use Tests\Architecture\Support\SourceFiles;

const ALLOWED_APP_DIRECTORIES = ['Modules', 'Providers', 'Shared'];

it('REQ-5.2 keeps app/ to Modules, Shared and Providers', function (): void {
    expect(array_diff(SourceFiles::topLevelDirectories('app'), ALLOWED_APP_DIRECTORIES))->toBe([]);
});

it('REQ-5.2 keeps PHP files out of the root of app/', function (): void {
    expect(glob(SourceFiles::root().'/app/*.php') ?: [])->toBe([]);
});

it('REQ-5.8 keeps controllers under Controllers/, never Http/', function (): void {
    $withHttp = array_filter(
        ModuleMap::modules(),
        fn (string $module): bool => is_dir(SourceFiles::root()."/app/Modules/{$module}/Http"),
    );

    expect(array_values($withHttp))->toBe([]);
});
