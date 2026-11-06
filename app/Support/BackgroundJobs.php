<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Background jobs (System Settings ▸ Background Jobs).
 *
 * Emails are sent in one of two ways:
 *  - Queue worker OFF (default): right after the page has responded (dispatchAfterResponse) — no worker needed.
 *  - Queue worker ON: on the database queue "emails". The Laravel scheduler (one cron job running
 *    `schedule:run` every minute) starts `queue:work --stop-when-empty` each minute, which adds automatic
 *    retries for temporary SMTP2GO problems.
 *
 * Both the scheduler and the worker "check in" to background_heartbeats, so the page can show whether they run.
 * If the worker is switched on but the scheduler has not checked in for STALE_AFTER seconds, emails fall back
 * to "right after the page" — nothing waits for a worker that is not running.
 */
class BackgroundJobs
{
    public const SCHEDULER = 'scheduler';
    public const WORKER = 'email_worker';

    public const CONNECTION = 'database';
    public const QUEUE = 'emails';

    /** Seconds without a check-in before a process counts as not running. */
    public const STALE_AFTER = 180;

    /** Minutes kept for the "last 60 minutes" strip. */
    public const RECENT_MINUTES = 60;

    /** Set while "Send waiting emails now" runs the worker inside a web request — not a cron check-in. */
    public static bool $manualRun = false;

    /** Worker check-ins are written at most this often per process (Looping fires every few seconds). */
    protected static ?int $lastWorkerBeat = null;

    public static function workerEnabled(): bool
    {
        return Settings::bool('queue.worker_enabled');
    }

    /** Queue the email for the worker? Only while switched on and the scheduler is alive. */
    public static function useWorker(): bool
    {
        return static::workerEnabled() && static::alive(static::SCHEDULER);
    }

    /** 'worker' | 'fallback' | 'direct' */
    public static function mode(): string
    {
        if (!static::workerEnabled()) {
            return 'direct';
        }

        return static::alive(static::SCHEDULER) ? 'worker' : 'fallback';
    }

    public static function modeLabel(): string
    {
        return [
            'worker' => 'by the queue worker (database queue "emails", with retries)',
            'fallback' => 'right after the page responds — the queue worker is on but the scheduler is not running',
            'direct' => 'right after the page responds (no queue worker needed)',
        ][static::mode()];
    }

    /** The scheduler runs the worker while it is switched on, or to finish emails still waiting in the queue. */
    public static function shouldRunWorker(): bool
    {
        return static::workerEnabled() || static::waiting() > 0;
    }

    public static function alive(string $name): bool
    {
        $row = static::row($name);

        return $row && $row->last_beat_at && Carbon::parse($row->last_beat_at)->gt(now()->subSeconds(static::STALE_AFTER));
    }

    // ------------------------------------------------------------------ Check-ins

    /** One check-in ("hit") of the scheduler or the worker. */
    public static function beat(string $name): void
    {
        try {
            $now = now();
            $row = static::row($name);
            $minute = $now->format('Y-m-d H:i');
            $from = $now->copy()->subMinutes(static::RECENT_MINUTES - 1)->format('Y-m-d H:i');
            $recent = collect($row ? (array) json_decode((string) $row->recent, true) : [])
                ->push($minute)->unique()->filter(fn ($m) => $m >= $from)->values()->all();
            $sameDay = $row && $row->beats_date === $now->toDateString();

            DB::table('background_heartbeats')->updateOrInsert(['name' => $name], [
                'last_beat_at' => $now,
                'beats_total' => ($row->beats_total ?? 0) + 1,
                'beats_today' => $sameDay ? $row->beats_today + 1 : 1,
                'beats_date' => $now->toDateString(),
                'recent' => json_encode($recent),
                'updated_at' => $now,
                'created_at' => $row->created_at ?? $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Background heartbeat \"{$name}\" not saved: " . $e->getMessage());
        }
    }

    /** Queue worker loop (Looping event) — one check-in per 30 seconds per worker process. */
    public static function workerLooping(string $queue): void
    {
        if (static::$manualRun || !in_array(static::QUEUE, explode(',', $queue), true)) {
            return;
        }
        if (static::$lastWorkerBeat && time() - static::$lastWorkerBeat < 30) {
            return;
        }
        static::$lastWorkerBeat = time();
        static::beat(static::WORKER);
    }

    /** A job on the "emails" queue finished (JobProcessed) or failed for good (JobFailed). */
    public static function jobFinished(bool $failed, ?string $error = null): void
    {
        try {
            DB::table('background_heartbeats')->updateOrInsert(['name' => static::WORKER], ['updated_at' => now()]);
            $q = DB::table('background_heartbeats')->where('name', static::WORKER);
            $failed
                ? $q->update(['jobs_failed' => DB::raw('jobs_failed + 1'), 'last_job_at' => now(), 'last_error' => Str::limit((string) $error, 500)])
                : $q->update(['jobs_processed' => DB::raw('jobs_processed + 1'), 'last_job_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Background job counter not saved: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ Status

    protected static function row(string $name): ?object
    {
        try {
            return DB::table('background_heartbeats')->where('name', $name)->first();
        } catch (\Throwable $e) {
            return null; // table not migrated yet
        }
    }

    /** Emails waiting on the "emails" queue. */
    public static function waiting(): int
    {
        try {
            return (int) DB::table('jobs')->where('queue', static::QUEUE)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Everything the Background Jobs tab shows for the scheduler or the worker.
     * state: running | late | down | never
     */
    public static function status(string $name): array
    {
        $row = static::row($name);
        $last = $row && $row->last_beat_at ? Carbon::parse($row->last_beat_at) : null;
        $age = $last ? $last->diffInSeconds(now()) : null;
        $state = !$last ? 'never' : ($age <= static::STALE_AFTER ? 'running' : ($age <= 900 ? 'late' : 'down'));

        $recent = $row ? (array) json_decode((string) $row->recent, true) : [];
        $strip = [];
        for ($i = static::RECENT_MINUTES - 1; $i >= 0; $i--) {
            $minute = now()->subMinutes($i);
            $strip[] = ['label' => $minute->format('H:i'), 'hit' => in_array($minute->format('Y-m-d H:i'), $recent, true)];
        }

        return [
            'state' => $state,
            'last' => $last,
            'today' => $row && $row->beats_date === now()->toDateString() ? (int) $row->beats_today : 0,
            'total' => (int) ($row->beats_total ?? 0),
            'hour' => count(array_filter($strip, fn ($m) => $m['hit'])),
            'strip' => $strip,
            'processed' => (int) ($row->jobs_processed ?? 0),
            'failed' => (int) ($row->jobs_failed ?? 0),
            'last_job' => $row && $row->last_job_at ? Carbon::parse($row->last_job_at) : null,
            'last_error' => $row->last_error ?? null,
        ];
    }

    /** The "emails" queue: waiting jobs, the oldest one, and jobs that failed for good. */
    public static function queueStatus(): array
    {
        try {
            $oldest = DB::table('jobs')->where('queue', static::QUEUE)->min('created_at');
            $failed = (int) DB::table('failed_jobs')->where('queue', static::QUEUE)->count();
        } catch (\Throwable $e) {
            $oldest = null;
            $failed = 0;
        }

        return [
            'waiting' => static::waiting(),
            'oldest' => $oldest ? Carbon::createFromTimestamp($oldest) : null,
            'failed' => $failed,
        ];
    }

    /** The cron line for Plesk ▸ Scheduled Tasks (or crontab -e). */
    public static function cronCommand(): string
    {
        $plesk = '/opt/plesk/php/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '/bin/php';
        $php = trim((string) config('app.php_cli_binary'))
            ?: (@is_file($plesk) ? $plesk : (PHP_OS_FAMILY === 'Windows' ? 'php' : PHP_BINDIR . '/php'));

        return '* * * * * ' . $php . ' ' . base_path('artisan') . ' schedule:run >> /dev/null 2>&1';
    }
}
