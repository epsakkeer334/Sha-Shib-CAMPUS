<?php

namespace App\Services;

use App\Models\Admin\NotificationLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Email / SMS notifications, each recorded in notifications_log (the Email & SMS Log).
 * Email: always through App\Services\EmailService (SMTP2GO, sent in the background) — modules with a
 * template call EmailService directly; send() is for one-off messages with their own subject and text.
 * SMS is logged as pending until a gateway is chosen (plan.md open question 3).
 */
class NotificationService
{
    public function __construct(protected EmailService $emails)
    {
    }

    /**
     * @param  Model  $notifiable  user or student (needs email / phone attributes)
     * @param  string $eventType   er_issued, exam_approved, result_published, mou_expiry ...
     * @param  array  $channels    any of 'email', 'sms'
     */
    public function send(Model $notifiable, string $eventType, string $subject, string $message, array $channels = ['email'], array $payload = []): void
    {
        foreach ($channels as $channel) {
            if ($channel === 'email') {
                $this->emails->sendCustom($notifiable, $subject, $message, ['event_type' => $eventType]);
                continue;
            }

            NotificationLog::create([
                'institute_id' => $notifiable->institute_id ?? null,
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->getKey(),
                'channel' => $channel,
                'event_type' => $eventType,
                'recipient' => $notifiable->phone,
                'subject' => $subject,
                'payload' => array_merge($payload, ['subject' => $subject, 'message' => $message]),
                'status' => 'pending',
                'error' => 'SMS gateway not configured yet.',
            ]);
        }
    }
}
