<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
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

    /**
     * Sign in on the side the user belongs to, like a real browser: students on the admissions portal
     * (`student` guard), staff on the admin panel (`web`). Pass a guard to choose explicitly.
     */
    public function be(UserContract $user, $guard = null)
    {
        $guard ??= method_exists($user, 'hasRole') && $user->hasRole('student') ? 'student' : 'web';

        return parent::be($user, $guard);
    }

    /**
     * Like a real request, every test request starts on the default `web` guard; portal routes switch to
     * `student` themselves (UseStudentGuard). Without this a portal request would leak into the next one.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->shouldUse('web');

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
