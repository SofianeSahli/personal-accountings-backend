<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $config = $app->make('config');
        $connection = $config->string('database.default');
        TestDatabaseGuard::ensure($config->string("database.connections.{$connection}.database"));

        return $app;
    }
}
