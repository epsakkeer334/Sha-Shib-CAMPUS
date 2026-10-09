<?php

namespace App\Services;

use App\Models\Admin\NotificationLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * Sends CAMP notifications and records every attempt in notifications_log.
 * Email: sending is commented out until the SMTP2GO mail service is set up (search "SMTP2GO");
 * emails are logged as pending. SMS is logged as pending until a gateway is chosen (plan.md open question 3).
 */
class NotificationService
{
    /**
     * @param  Model  $notifiable  user or student (needs email / phone attributes)
     * @param  string $eventType   er_issued, exam_approved, result_published, mou_expiry ...
     * @param  array  $channels    any of 'email', 'sms'
     */
    public function send(Model $notifiable, string $eventType, string $subject, string $message, array $channels = ['email'], array $payload = []): void
    {
        foreach ($channels as $channel) {
            $log = NotificationLog::create([
                'institute_id' => $notifiable->institute_id ?? null,
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->getKey(),
                'channel' => $channel,
                'event_type' => $eventType,
                'recipient' => $channel === 'sms' ? $notifiable->phone : $notifiable->email,
                'payload' => array_merge($payload, ['subject' => $subject, 'message' => $message]),
                'status' => 'pending',
            ]);

            if ($channel === 'email') {
                $this->sendEmail($log, $subject, $message);
            } else {
                $log->update(['error' => 'SMS gateway not configured yet.']);
            }
        }
    }

    protected function sendEmail(NotificationLog $log, string $subject, string $message): void
    {
        if (!$log->recipient) {
            $log->update(['status' => 'failed', 'error' => 'No email address.']);
            return;
        }

        // SMTP2GO: email sending is switched off until the SMTP2GO mail service is purchased and set up.
        // The email stays in notifications_log as "pending" so it is visible (and can be re-sent later).
        // To go live: set the SMTP2GO MAIL_* values in .env, then uncomment the block below and remove the line after it.
        //
        // try {
        //     Mail::raw($message, fn ($mail) => $mail->to($log->recipient)->subject($subject));
        //     $log->update(['status' => 'sent', 'sent_at' => now()]);
        // } catch (\Throwable $e) {
        //     $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        // }
        $log->update(['error' => 'Email not sent: mail service (SMTP2GO) not set up yet.']);
    }
}
