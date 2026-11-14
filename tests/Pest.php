<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('../app/Shared/Tests/Feature', '../app/Modules/*/Tests');

function fakeDeviceId(): string
{
    return (string) Str::uuid();
}
