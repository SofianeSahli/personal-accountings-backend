<?php

declare(strict_types=1);

use Tests\Architecture\Support\ModuleMap;
use Tests\Architecture\Support\SourceFiles;

const OTHER_MODEL_PATTERN = '/App\\\\Modules\\\\(\w+)\\\\Models\\\\(\w+)/';

it('REQ-5.5 never lets a module use another module\'s models, except Identity\'s User', function (): void {
    $violations = [];

    foreach (ModuleMap::modules() as $module) {
        foreach (SourceFiles::read(["app/Modules/{$module}"]) as $path => $contents) {
            preg_match_all(OTHER_MODEL_PATTERN, $contents, $matches, PREG_SET_ORDER);

            foreach ($matches as [$reference, $owner]) {
                if ($owner !== $module && $reference !== ModuleMap::SHARED_USER_MODEL) {
                    $violations[] = "{$path} uses {$reference}";
                }
            }
        }
    }

    expect($violations)->toBe([]);
});
