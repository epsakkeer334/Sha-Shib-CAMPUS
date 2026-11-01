<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Re-apply these route middlewares on Livewire action requests, so role/permission
        // checks on a page also protect its buttons (save, delete ...).
        Livewire::addPersistentMiddleware([
            \App\Http\Middleware\UseStudentGuard::class, // first: portal clicks must read the student login
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\EnsureStudent::class,
            \App\Http\Middleware\RedirectStudentsToPortal::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
            \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        // System Settings → SMTP mailer, session lifetime (falls back to .env / config when not set).
        // Skipped while `config:cache` / `optimize` builds the cache, so the decrypted SMTP password
        // is never written into bootstrap/cache/config.php (it is applied on every request instead).
        $buildingConfigCache = $this->app->runningInConsole()
            && in_array($_SERVER['argv'][1] ?? null, ['config:cache', 'optimize'], true);
        if (!$buildingConfigCache) {
            \App\Support\Settings::applyRuntimeConfig();
        }
    }
}
