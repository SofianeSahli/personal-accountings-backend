<?php

declare(strict_types=1);

use Tests\Architecture\Support\AppClasses;
use Tests\Architecture\Support\SourceFiles;

const STRICT_TYPES_PATTERN = '/^<\?php\s+declare\(strict_types=1\);/';

arch('REQ-5.9 every App class declares strict types')->expect('App')->toUseStrictTypes();

arch('REQ-5.9 every App class is final')->expect('App')->classes()->toBeFinal();

it('REQ-5.9 every PHP file under app/ starts with declare(strict_types=1)', function (): void {
    $missing = array_keys(array_filter(
        SourceFiles::read(['app']),
        fn (string $contents): bool => preg_match(STRICT_TYPES_PATTERN, $contents) !== 1,
    ));

    expect($missing)->toBe([]);
});

it('REQ-5.9 every concrete class under app/ is final', function (): void {
    $notFinal = [];

    foreach (AppClasses::all() as $class) {
        if (! $class->isInterface() && ! $class->isTrait() && ! $class->isEnum() && ! $class->isFinal()) {
            $notFinal[] = $class->getName();
        }
    }

    expect($notFinal)->toBe([]);
});
