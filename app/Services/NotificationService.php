<?php

namespace App\Services;

use App\Models\Admin\NotificationLog;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * Sends CAMP notifications and records every attempt in notifications_log.
 * Email: sent only when System Settings → Email is switched on and the SMTP details are filled in
 * (App\Support\Settings::emailEnabled); otherwise it is logged as pending so it can be re-sent later.
 * SMS is logged as pending until a gateway is chosen (plan.md open question 3).
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

        // Switched off (default) or SMTP not filled in: keep it in notifications_log as "pending".
        if (!Settings::emailEnabled()) {
            $log->update(['error' => 'Email not sent: email sending is switched off in System Settings.']);
            return;
        }

        try {
            Settings::applyRuntimeConfig();
            Settings::refreshMailer();
            Mail::raw($message, fn ($mail) => $mail->to($log->recipient)->subject($subject));
            $log->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }
}
