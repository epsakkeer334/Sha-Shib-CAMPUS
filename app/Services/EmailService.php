<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\Admin\EmailTemplate;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteEmailSetting;
use App\Models\Admin\NotificationLog;
use App\Models\Admin\Student;
use App\Services\Email\Smtp2GoClient;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The one place CAMP sends email from — every module calls this, nothing else sends mail.
 *
 *  1. picks the wording: institute version → group version → default (config/email_templates.php)
 *  2. fills the {placeholders} and wraps the text in the branded layout (resources/views/emails/layout.blade.php)
 *  3. writes the email to notifications_log (the Email & SMS Log) as "pending"
 *  4. hands it to SendEmailJob, which calls SMTP2GO in the background — the user never waits:
 *     sent right after the response is returned, or — queue worker switched on in System Settings ▸ Background
 *     Jobs — by the scheduled worker (with retries); see App\Support\BackgroundJobs. The log row then becomes "sent" (with the SMTP2GO email id) or "failed" (with the error).
 *
 * Switched off in System Settings ▸ Email, or SMTP2GO not set in .env: the row stays "pending" with the reason,
 * and can be sent later from the log (Retry).
 */
class EmailService
{
    public function __construct(protected Smtp2GoClient $client)
    {
    }

    /** Sending is switched on and SMTP2GO is configured. */
    public function enabled(): bool
    {
        return Settings::emailEnabled();
    }

    // ------------------------------------------------------------------ Sending

    /**
     * Template email to a student (student placeholders filled in).
     */
    public function toStudent(string $key, Student $student, array $vars = []): NotificationLog
    {
        return $this->sendTemplate($key, $student, $vars + $this->studentVars($student), ['institute_id' => $student->institute_id]);
    }

    /**
     * Template email to a user or student.
     *
     * @param  array  $options  institute_id, email (send to this address instead), event_type,
     *                          now (send and wait — test buttons only), force (send even if the template is switched off)
     */
    public function sendTemplate(string $key, Model $to, array $vars = [], array $options = []): NotificationLog
    {
        $instituteId = array_key_exists('institute_id', $options) ? $options['institute_id'] : ($to->institute_id ?? null);
        $template = $this->template($key, $instituteId);
        $vars = $vars + $this->recipientVars($to) + $this->commonVars($instituteId);
        $rendered = $this->render($template, $vars, $instituteId);

        $log = $this->createLog($to, $options['email'] ?? $to->email, $instituteId, $options['event_type'] ?? $key, $rendered, [
            'template_key' => $key,
            'email_template_id' => optional($template['model'])->id,
        ]);

        if (!$template['status'] && empty($options['force'])) {
            $log->update(['error' => 'Not sent: this email is switched off in Email Templates.']);

            return $log;
        }

        return !empty($options['now']) ? $this->sendWaiting($log) : $this->queue($log);
    }

    /**
     * An email with its own subject and text (no template), on the same layout and log.
     *
     * @param  array  $options  institute_id, email, event_type, now (send and wait — test buttons only),
     *                          rendered (an already rendered email from render(); $body is then ignored)
     */
    public function sendCustom(Model $to, string $subject, string $body, array $options = []): NotificationLog
    {
        $instituteId = array_key_exists('institute_id', $options) ? $options['institute_id'] : ($to->institute_id ?? null);
        $rendered = $options['rendered'] ?? $this->render(['subject' => $subject, 'body' => $body, 'button_text' => null, 'button_url' => null], $this->recipientVars($to) + $this->commonVars($instituteId), $instituteId);
        $rendered['subject'] = $subject !== '' ? $subject : $rendered['subject'];

        $log = $this->createLog($to, $options['email'] ?? $to->email, $instituteId, $options['event_type'] ?? 'custom', $rendered, []);

        return !empty($options['now']) ? $this->sendWaiting($log) : $this->queue($log);
    }

    /**
     * Send a pending / failed email again (Email & SMS Log ▸ Retry).
     */
    public function retry(NotificationLog $log): NotificationLog
    {
        if ($log->channel !== 'email' || $log->status === 'sent') {
            return $log;
        }

        $log->update(['status' => 'pending', 'error' => null]);

        return $this->queue($log);
    }

    /**
     * Called by SendEmailJob: one attempt through SMTP2GO, result written to the log.
     */
    public function deliver(NotificationLog $log, bool $ignoreSwitch = false): array
    {
        $log->refresh();
        if ($log->status === 'sent') {
            return ['ok' => true, 'retryable' => false, 'error' => null];
        }

        if (!($ignoreSwitch ? $this->client->configured() : $this->enabled())) {
            $log->update(['error' => $this->offReason($ignoreSwitch)]);

            return ['ok' => false, 'retryable' => false, 'error' => $log->error];
        }

        $payload = (array) $log->payload;
        $result = $this->client->send([
            'to' => $log->recipient,
            'to_name' => $payload['to_name'] ?? null,
            'subject' => $log->subject ?? ($payload['subject'] ?? ''),
            'html' => $payload['html'] ?? nl2br(e($payload['message'] ?? '')),
            'text' => $payload['message'] ?? null,
            'sender_name' => $payload['sender_name'] ?? null,
            'reply_to' => $payload['reply_to'] ?? null,
        ]);

        $log->forceFill([
            'provider' => 'smtp2go',
            'attempts' => $log->attempts + 1,
            'last_attempt_at' => now(),
        ]);

        if ($result['ok']) {
            $log->forceFill(['status' => 'sent', 'sent_at' => now(), 'provider_message_id' => $result['message_id'], 'error' => null])->save();
        } else {
            $log->forceFill(['status' => 'failed', 'error' => $result['error']])->save();
        }

        return $result;
    }

    /**
     * Sends now and waits for SMTP2GO's answer — only for the test buttons (option 'now'), where the
     * admin is waiting for the result. Workflow emails always go through queue().
     */
    protected function sendWaiting(NotificationLog $log): NotificationLog
    {
        if ($log->status === 'failed' && !$log->recipient) {
            return $log;
        }

        // A test is allowed while sending is switched off, to check SMTP2GO before going live.
        $this->deliver($log, true);

        return $log->refresh();
    }

    protected function createLog(Model $to, ?string $email, ?int $instituteId, string $event, array $rendered, array $extra): NotificationLog
    {
        $email = trim((string) $email);

        $log = NotificationLog::create($extra + [
            'institute_id' => $instituteId,
            'notifiable_type' => get_class($to),
            'notifiable_id' => $to->getKey(),
            'channel' => 'email',
            'event_type' => $event,
            'recipient' => $email !== '' ? $email : null,
            'subject' => Str::limit($rendered['subject'], 250, ''),
            'payload' => [
                'subject' => $rendered['subject'],
                'message' => $rendered['text'],
                'html' => $rendered['html'],
                'to_name' => $this->nameOf($to),
                'sender_name' => $rendered['sender_name'],
                'reply_to' => $rendered['reply_to'],
            ],
            'status' => 'pending',
            'provider' => 'smtp2go',
        ]);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $log->update(['status' => 'failed', 'error' => $email === '' ? 'No email address.' : 'Invalid email address.']);
        }

        return $log;
    }

    protected function queue(NotificationLog $log): NotificationLog
    {
        if ($log->status === 'failed' && !$log->recipient) {
            return $log; // nothing to send to
        }

        if (!$this->enabled()) {
            $log->update(['error' => $this->offReason()]);

            return $log;
        }

        SendEmailJob::dispatchInBackground($log->id);

        return $log;
    }

    protected function offReason(bool $ignoreSwitch = false): string
    {
        if (!$ignoreSwitch && !Settings::bool('mail.enabled')) {
            return 'Not sent: email sending is switched off in System Settings ▸ Email.';
        }

        return 'Not sent: SMTP2GO is not configured (SMTP2GO_API_KEY / SMTP2GO_SENDER_EMAIL in .env).';
    }

    // ------------------------------------------------------------------ Templates

    /** Every template key → catalogue entry. */
    public function catalogue(): array
    {
        return config('email_templates.templates', []);
    }

    /**
     * The wording used for this key and institute: [subject, body, button_text, button_url, status, source, model].
     * source: 'institute' (its own version), 'group' (Super Admin's version) or 'default' (built in).
     */
    public function template(string $key, ?int $instituteId): array
    {
        $default = $this->catalogue()[$key] ?? null;
        abort_if($default === null, 500, "Unknown email template [{$key}].");

        $rows = EmailTemplate::where('key', $key)
            ->where(fn ($q) => $q->whereNull('institute_id')->when($instituteId, fn ($w) => $w->orWhere('institute_id', $instituteId)))
            ->get();

        $row = ($instituteId ? $rows->first(fn ($r) => (int) $r->institute_id === (int) $instituteId) : null)
            ?? $rows->first(fn ($r) => $r->institute_id === null);

        if ($row) {
            return [
                'subject' => $row->subject, 'body' => $row->body, 'button_text' => $row->button_text, 'button_url' => $row->button_url,
                'status' => (bool) $row->status, 'source' => $row->institute_id ? 'institute' : 'group', 'model' => $row,
            ];
        }

        return [
            'subject' => $default['subject'], 'body' => $default['body'],
            'button_text' => $default['button'][0] ?? null, 'button_url' => $default['button'][1] ?? null,
            'status' => true, 'source' => 'default', 'model' => null,
        ];
    }

    /** Placeholders a template may use: [name => description]. */
    public function placeholders(string $key): array
    {
        $entry = $this->catalogue()[$key] ?? [];
        $list = (array) ($entry['placeholders'] ?? []);
        if (($entry['audience'] ?? '') === 'student') {
            $list += config('email_templates.student_placeholders', []);
        }

        return $list + config('email_templates.common_placeholders', []);
    }

    /**
     * Fill the placeholders and build the email: [subject, html, text, sender_name, reply_to].
     */
    public function render(array $template, array $vars, ?int $instituteId): array
    {
        $vars = array_map(fn ($v) => is_array($v) ? '- ' . implode("\n- ", $v) : (string) ($v ?? ''), $vars);

        $subject = trim(preg_replace('/\s+/', ' ', $this->fill($template['subject'], $vars)));
        $body = $this->fill(str_replace("\r\n", "\n", (string) $template['body']), $vars);

        $button = null;
        if (trim((string) $template['button_text']) !== '' && trim((string) $template['button_url']) !== '') {
            $url = trim($this->fill($template['button_url'], $vars));
            if (preg_match('#^https?://#i', $url)) {
                $button = ['text' => $this->fill($template['button_text'], $vars), 'url' => $url];
            }
        }

        $institute = $instituteId ? Institute::find($instituteId) : null;
        $settings = $instituteId ? InstituteEmailSetting::where('institute_id', $instituteId)->first() : null;
        $signature = trim((string) optional($settings)->signature);
        $siteName = (string) Settings::get('general.site_name');

        $html = view('emails.layout', [
            'subject' => $subject,
            'bodyHtml' => $this->formatHtml($body),
            'button' => $button,
            'signatureHtml' => $signature !== '' ? $this->formatHtml($this->fill($signature, $vars)) : null,
            'institute' => $institute,
            'siteName' => $siteName,
            'organisation' => (string) Settings::get('general.organisation'),
            'supportEmail' => (string) Settings::get('general.support_email'),
            'footerText' => (string) Settings::get('general.footer_text'),
        ])->render();

        $text = trim(str_replace('**', '', $body))
            . ($button ? "\n\n{$button['text']}: {$button['url']}" : '')
            . ($signature !== '' ? "\n\n" . str_replace('**', '', $this->fill($signature, $vars)) : '')
            . "\n\n— " . ($institute->name ?? $siteName);

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
            'sender_name' => trim((string) optional($settings)->sender_name) ?: ($institute->name ?? $this->client->defaultSenderName()),
            'reply_to' => $this->replyTo($institute, $settings),
        ];
    }

    /** Example values for the preview / test send of a template. */
    public function sampleVars(string $key, ?int $instituteId, ?Model $recipient = null): array
    {
        $samples = [
            'student_name' => 'Arjun Menon', 'first_name' => 'Arjun', 'recipient_name' => 'Arjun Menon', 'er_number' => 'ER-2026-00042',
            'course' => 'Aircraft Maintenance Engineering (AME)', 'deadline' => now()->addDays(14)->format('d M Y'),
            'reason' => 'The scan is not clear — the name and date of birth cannot be read.', 'document' => '10th Marksheet',
            'documents' => ['Medical Certificate', '12th Marksheet'], 'amount' => money_inr(25000), 'fee' => 'Semester 1 tuition fee',
            'receipt' => 'RCPT-2026-00125', 'reference' => 'UPI 412345678901', 'sent_at' => now()->format('d M Y, h:i A'),
        ];

        $vars = $samples;
        if ($recipient) {
            $vars = $this->recipientVars($recipient) + $vars;
        }

        return $vars + $this->commonVars($instituteId);
    }

    // ------------------------------------------------------------------ Placeholder values

    public function studentVars(Student $student): array
    {
        return [
            'student_name' => $student->full_name,
            'first_name' => $student->first_name,
            'recipient_name' => $student->full_name,
            'er_number' => $student->er_number,
            'course' => optional($student->course)->name,
            'deadline' => $student->formatted_onboarding_deadline,
        ];
    }

    protected function recipientVars(Model $to): array
    {
        if ($to instanceof Student) {
            return ['recipient_name' => $to->full_name, 'first_name' => $to->first_name];
        }

        $name = $this->nameOf($to);

        return ['recipient_name' => $name, 'first_name' => Str::before($name, ' ') ?: $name];
    }

    public function commonVars(?int $instituteId): array
    {
        $institute = $instituteId ? Institute::find($instituteId) : null;

        return [
            'institute_name' => $institute->name ?? (string) Settings::get('general.organisation'),
            'institute_email' => $institute->email ?? (string) Settings::get('general.support_email'),
            'institute_phone' => $institute->phone ?? (string) Settings::get('general.support_phone'),
            'site_name' => (string) Settings::get('general.site_name'),
            'portal_url' => route('portal.login'),
            'support_email' => (string) Settings::get('general.support_email'),
        ];
    }

    protected function nameOf(Model $to): string
    {
        return (string) ($to instanceof Student ? $to->full_name : ($to->name ?? ''));
    }

    protected function replyTo(?Institute $institute, ?InstituteEmailSetting $settings): ?string
    {
        foreach ([optional($settings)->reply_to, optional($institute)->email, Settings::get('general.support_email')] as $address) {
            if ($address && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                return $address;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ Formatting

    /** {name} → value. Unknown placeholders stay as typed, so they show up in the preview. */
    public function fill(string $text, array $vars): string
    {
        return preg_replace_callback('/\{([a-z0-9_]+)\}/i', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $text);
    }

    /**
     * Plain text → safe email HTML: paragraphs, line breaks, "- " bullet lists and **bold**.
     */
    public function formatHtml(string $text): string
    {
        $html = '';
        foreach (preg_split('/\n\s*\n/', trim($text)) as $block) {
            $lines = array_values(array_filter(array_map('rtrim', explode("\n", $block)), fn ($l) => $l !== ''));
            if (!$lines) {
                continue;
            }

            $isList = collect($lines)->every(fn ($l) => str_starts_with(ltrim($l), '- '));
            if ($isList) {
                $items = array_map(fn ($l) => '<li style="margin:0 0 6px;">' . $this->inline(substr(ltrim($l), 2)) . '</li>', $lines);
                $html .= '<ul style="margin:0 0 16px;padding-left:20px;color:#2F2E5A;font-size:15px;line-height:24px;">' . implode('', $items) . '</ul>';
            } else {
                $html .= '<p style="margin:0 0 16px;color:#2F2E5A;font-size:15px;line-height:24px;">' . implode('<br>', array_map(fn ($l) => $this->inline($l), $lines)) . '</p>';
            }
        }

        return $html;
    }

    protected function inline(string $line): string
    {
        return preg_replace('/\*\*(.+?)\*\*/s', '<strong style="color:#1D1C4C;">$1</strong>', e($line));
    }
}
