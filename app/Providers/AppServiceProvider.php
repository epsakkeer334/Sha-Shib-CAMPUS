<?php

namespace App\Providers;

use App\Support\BackgroundJobs;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
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

        // System Settings ▸ Background Jobs: email worker check-ins and job counters.
        Event::listen(Looping::class, fn (Looping $e) => BackgroundJobs::workerLooping((string) $e->queue));
        Event::listen(JobProcessed::class, function (JobProcessed $e) {
            if ($e->job->getQueue() === BackgroundJobs::QUEUE && !$e->job->isReleased()) {
                BackgroundJobs::jobFinished(false);
            }
        });
        Event::listen(JobFailed::class, function (JobFailed $e) {
            if ($e->job->getQueue() === BackgroundJobs::QUEUE) {
                BackgroundJobs::jobFinished(true, $e->exception->getMessage());
            }
        });

        // System Settings → session lifetime (falls back to .env / config when not set).
        // Skipped while `config:cache` / `optimize` builds the cache, so saved settings are never baked
        // into bootstrap/cache/config.php (they are applied on every request instead).
        $buildingConfigCache = $this->app->runningInConsole()
            && in_array($_SERVER['argv'][1] ?? null, ['config:cache', 'optimize'], true);
        if (!$buildingConfigCache) {
            \App\Support\Settings::applyRuntimeConfig();
        }
    }
}
