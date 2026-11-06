<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin client for the SMTP2GO API — POST https://api.smtp2go.com/v3/email/send.
 * Credentials come from config/services.php (SMTP2GO_* in .env), never from the database.
 * Only App\Services\EmailService should call this; modules use EmailService.
 */
class Smtp2GoClient
{
    public function configured(): bool
    {
        return (string) config('services.smtp2go.api_key') !== '' && filter_var(config('services.smtp2go.sender_email'), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function senderEmail(): string
    {
        return (string) config('services.smtp2go.sender_email');
    }

    public function defaultSenderName(): string
    {
        return (string) config('services.smtp2go.sender_name');
    }

    /** "api-…1A2B" for display; never the whole key. */
    public function maskedKey(): string
    {
        $key = (string) config('services.smtp2go.api_key');

        return $key === '' ? '' : Str::substr($key, 0, 4) . '…' . Str::substr($key, -4);
    }

    /**
     * @param  array  $message  to (email), to_name, subject, html, text, sender_name, reply_to
     * @return array{ok: bool, message_id: ?string, error: ?string, http_status: ?int, retryable: bool}
     */
    public function send(array $message): array
    {
        if (!$this->configured()) {
            return $this->fail('SMTP2GO is not configured: set SMTP2GO_API_KEY and SMTP2GO_SENDER_EMAIL in .env.', null, false);
        }

        $body = [
            'sender' => $this->mailbox($this->senderEmail(), $message['sender_name'] ?? $this->defaultSenderName()),
            'to' => [$this->mailbox($message['to'], $message['to_name'] ?? null)],
            'subject' => $message['subject'],
            'html_body' => $message['html'],
            'text_body' => $message['text'] ?? strip_tags($message['html']),
        ];
        if (!empty($message['reply_to'])) {
            $body['custom_headers'] = [['header' => 'Reply-To', 'value' => $message['reply_to']]];
        }

        try {
            $response = Http::timeout((int) config('services.smtp2go.timeout', 20))
                ->acceptJson()
                ->withHeaders(['X-Smtp2go-Api-Key' => (string) config('services.smtp2go.api_key')])
                ->post((string) config('services.smtp2go.endpoint'), $body);
        } catch (\Throwable $e) {
            // Timeout / DNS / connection refused: worth trying again later.
            return $this->fail('Could not reach SMTP2GO: ' . Str::limit($e->getMessage(), 300), null, true);
        }

        $data = (array) $response->json('data', []);

        if ($response->successful() && (int) ($data['succeeded'] ?? 0) >= 1) {
            return ['ok' => true, 'message_id' => $data['email_id'] ?? null, 'error' => null, 'http_status' => $response->status(), 'retryable' => false];
        }

        $error = $data['error'] ?? collect($data['failures'] ?? [])->flatten()->filter()->implode('; ');
        $error = $error !== '' ? $error : 'SMTP2GO did not accept the email (HTTP ' . $response->status() . ').';
        if (!empty($data['error_code'])) {
            $error .= ' [' . $data['error_code'] . ']';
        }

        // Rate limits and server errors can pass on a later try; a bad key / sender / address will not.
        return $this->fail(Str::limit($error, 500), $response->status(), $response->status() === 429 || $response->serverError());
    }

    protected function mailbox(string $email, ?string $name): string
    {
        $name = trim(str_replace(['"', '<', '>', "\r", "\n"], '', (string) $name));

        return $name !== '' ? "\"{$name}\" <{$email}>" : $email;
    }

    protected function fail(string $error, ?int $status, bool $retryable): array
    {
        return ['ok' => false, 'message_id' => null, 'error' => $error, 'http_status' => $status, 'retryable' => $retryable];
    }
}
