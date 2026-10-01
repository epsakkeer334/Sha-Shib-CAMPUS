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
            \App\Http\Middleware\EnsureUserIsActive::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
            \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    }
}
