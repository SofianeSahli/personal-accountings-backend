<?php

declare(strict_types=1);

use Tests\Architecture\Support\SourceFiles;

const ENV_CALL_PATTERN = '/(?<![\w>:$\\\\])env\s*\(/';
const REMOVED_STORES_PATTERN = '/redis|memcached|dynamodb|\bsqs\b/i';

arch('REQ-5.10 App code never calls env()')->expect('env')->not->toBeUsedIn('App');

it('REQ-5.10 calls env() only inside config/', function (): void {
    $files = SourceFiles::read(
        ['app', 'bootstrap', 'database', 'routes', 'tests'],
        excluded: ['bootstrap/cache/', 'tests/Architecture/RepositoryGuardsTest.php'],
    );

    $offenders = array_keys(array_filter(
        $files,
        fn (string $contents): bool => preg_match(ENV_CALL_PATTERN, $contents) === 1,
    ));

    expect($offenders)->toBe([]);
});

it('REQ-3.6 leaves no trace of redis, memcached, dynamodb or sqs', function (): void {
    $files = SourceFiles::read(
        ['app', 'bootstrap', 'config', 'database', 'routes', 'tests', 'docker', 'composer.json', 'docker-compose.yml', 'Dockerfile.dev', '.env.example'],
        extensionPattern: '/.*/',
        excluded: ['bootstrap/cache/', 'tests/Architecture/RepositoryGuardsTest.php'],
    );

    $offenders = array_keys(array_filter(
        $files,
        fn (string $contents): bool => preg_match(REMOVED_STORES_PATTERN, $contents) === 1,
    ));

    expect($offenders)->toBe([]);
});
