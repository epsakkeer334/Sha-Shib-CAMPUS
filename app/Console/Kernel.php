<?php

namespace App\Console;

use App\Support\BackgroundJobs;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Needs one cron job: * * * * * php artisan schedule:run (System Settings ▸ Background Jobs shows the line).

        // Check-in, so System Settings can show that the scheduler runs.
        $schedule->call(fn () => BackgroundJobs::beat(BackgroundJobs::SCHEDULER))
            ->everyMinute()->name('camp:scheduler-heartbeat');

        // Email queue worker: sends what is waiting on the "emails" queue, then stops (no long-running process).
        $schedule->command('queue:work ' . BackgroundJobs::CONNECTION . ' --queue=' . BackgroundJobs::QUEUE . ' --stop-when-empty --max-time=50 --sleep=3 --tries=3')
            ->everyMinute()->withoutOverlapping(10)
            ->when(fn () => BackgroundJobs::shouldRunWorker())
            ->name('camp:email-worker');

        // Login Activity keeps one year (App\Models\Admin\LoginLog::KEEP_DAYS)
        $schedule->command('model:prune', ['--model' => [\App\Models\Admin\LoginLog::class]])
            ->dailyAt('02:30')->name('camp:prune-login-logs');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
