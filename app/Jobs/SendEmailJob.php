<?php

namespace App\Jobs;

use App\Models\Admin\NotificationLog;
use App\Services\EmailService;
use App\Support\BackgroundJobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one logged email (notifications_log row) through SMTP2GO, away from the user's request.
 * Only EmailService dispatches this.
 */
class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /** Seconds before the 2nd and 3rd try (queue worker only). */
    public $backoff = [60, 300];

    public $timeout = 60;

    public function __construct(public int $logId)
    {
    }

    /**
     * Queue worker switched on (System Settings ▸ Background Jobs) and the scheduler running: put it on the
     * database queue "emails" for the worker, after the surrounding DB transaction commits.
     * Otherwise: run right after the response has been sent to the browser — the page never waits for SMTP2GO.
     */
    public static function dispatchInBackground(int $logId): void
    {
        if (BackgroundJobs::useWorker()) {
            static::dispatch($logId)->onConnection(BackgroundJobs::CONNECTION)->onQueue(BackgroundJobs::QUEUE)->afterCommit();

            return;
        }

        static::dispatchAfterResponse($logId);
    }

    public function handle(EmailService $emails): void
    {
        $log = NotificationLog::find($this->logId);
        if (!$log) {
            return; // the action that wrote it was rolled back
        }

        $result = $emails->deliver($log);

        // On a real queue, try again later for temporary problems (timeouts, rate limits, SMTP2GO 5xx).
        if (!$result['ok'] && $result['retryable'] && $this->job && $this->job->getConnectionName() !== 'sync' && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
        }
    }
}
