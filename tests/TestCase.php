<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication {
        createApplication as bootApplication;
    }

    /**
     * Safety guard: refuse to run any test unless the database name ends in "_testing", so a test
     * (or RefreshDatabase) can never wipe the working database — e.g. when config is cached.
     */
    public function createApplication()
    {
        $app = $this->bootApplication();
        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (!str_ends_with($database, '_testing')) {
            throw new RuntimeException("Tests stopped: database \"{$database}\" is not a *_testing database. Run `php artisan config:clear` and check phpunit.xml.");
        }

        return $app;
    }
}
