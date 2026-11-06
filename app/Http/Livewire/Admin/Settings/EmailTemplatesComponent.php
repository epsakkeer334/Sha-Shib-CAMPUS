<?php

namespace App\Http\Livewire\Admin\Settings;

use App\Models\Admin\EmailTemplate;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteEmailSetting;
use App\Services\EmailService;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Administration ▸ System ▸ Email Templates (permission email_templates.manage).
 * The wording of every email CAMP sends (config/email_templates.php), with a live preview on the branded layout.
 *  - Super Admin: the group-wide version (all institutes) or any institute's own version.
 *  - Institute Admin: own institute only — its version overrides the group one; "Reset" goes back to it.
 * Per institute also: sender name, reply-to and signature (institute_email_settings).
 */
class EmailTemplatesComponent extends Component
{
    use RecordsAuditTrail;

    /** '' = group-wide (Super Admin only), otherwise an institute id. */
    public $scope = '';
    public $selectedKey = 'er_issued';

    // template form
    public $subject = '', $body = '', $buttonText = '', $buttonUrl = '';
    public $active = true;
    public $confirmReset = false;

    // institute sender settings
    public $senderName = '', $replyTo = '', $signature = '';

    public $testEmail = '';
    public ?array $testResult = null;

    protected $queryString = ['scope' => ['except' => ''], 'selectedKey' => ['except' => 'er_issued', 'as' => 'template']];

    public function mount()
    {
        $this->authorizeTemplates();
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            $this->scope = (string) $user->institute_id;
        } elseif ($this->scope !== '' && !Institute::whereKey($this->scope)->exists()) {
            $this->scope = '';
        }
        if (!array_key_exists($this->selectedKey, $this->catalogue())) {
            $this->selectedKey = array_key_first($this->catalogue());
        }
        $this->testEmail = (string) $user->email;
        $this->loadTemplate();
        $this->loadSettings();
    }

    public function hydrate()
    {
        $this->authorizeTemplates();
        if (!Auth::user()->isSuperAdmin()) {
            $this->scope = (string) Auth::user()->institute_id; // own institute only, whatever the browser sends
        }
    }

    protected function authorizeTemplates()
    {
        abort_unless(Auth::user() && Auth::user()->can('email_templates.manage'), 403);
    }

    protected function emails(): EmailService
    {
        return app(EmailService::class);
    }

    /** Templates this user may edit: staff emails are sent by the Super Admin only, so only they see those. */
    protected function catalogue(): array
    {
        $all = $this->emails()->catalogue();

        return Auth::user()->isSuperAdmin() ? $all : array_filter($all, fn ($t) => ($t['audience'] ?? '') !== 'staff');
    }

    protected function instituteId(): ?int
    {
        return $this->scope !== '' ? (int) $this->scope : null;
    }

    public function updatedScope()
    {
        if (!Auth::user()->isSuperAdmin()) {
            return;
        }
        if ($this->scope !== '' && !Institute::whereKey($this->scope)->exists()) {
            $this->scope = '';
        }
        $this->resetValidation();
        $this->testResult = null;
        $this->loadTemplate();
        $this->loadSettings();
    }

    public function select($key)
    {
        if (!array_key_exists($key, $this->catalogue())) {
            return;
        }
        $this->selectedKey = $key;
        $this->resetValidation();
        $this->testResult = null;
        $this->loadTemplate();
    }

    protected function loadTemplate(): void
    {
        $t = $this->emails()->template($this->selectedKey, $this->instituteId());
        $this->subject = $t['subject'];
        $this->body = $t['body'];
        $this->buttonText = (string) $t['button_text'];
        $this->buttonUrl = (string) $t['button_url'];
        $this->active = $t['status'];
        $this->confirmReset = false;
    }

    protected function loadSettings(): void
    {
        $settings = $this->instituteId() ? InstituteEmailSetting::where('institute_id', $this->instituteId())->first() : null;
        $this->senderName = (string) optional($settings)->sender_name;
        $this->replyTo = (string) optional($settings)->reply_to;
        $this->signature = (string) optional($settings)->signature;
    }

    /** The row edited in the current scope (null when the scope uses an inherited version). */
    protected function ownRow(): ?EmailTemplate
    {
        return EmailTemplate::where('key', $this->selectedKey)->forScope($this->instituteId())->first();
    }

    // ------------------------------------------------------------------ Save

    protected function rules()
    {
        return [
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:5000',
            'buttonText' => 'nullable|required_with:buttonUrl|string|max:60',
            'buttonUrl' => ['nullable', 'required_with:buttonText', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (trim((string) $value) !== '' && !preg_match('#^(https?://|\{[a-z_]+\})#i', trim($value))) {
                    $fail('The button link must start with https:// or a placeholder such as {portal_url}.');
                }
            }],
            'active' => 'boolean',
        ];
    }

    protected $validationAttributes = ['buttonText' => 'button text', 'buttonUrl' => 'button link'];

    public function save()
    {
        $this->validate();

        $row = $this->ownRow();
        $old = $row ? $row->only(['subject', 'body', 'button_text', 'button_url', 'status']) : null;
        $values = [
            'subject' => trim($this->subject),
            'body' => rtrim(str_replace("\r\n", "\n", $this->body)),
            'button_text' => trim((string) $this->buttonText) ?: null,
            'button_url' => trim((string) $this->buttonUrl) ?: null,
            'status' => (bool) $this->active,
            'updated_by' => Auth::id(),
        ];

        if ($row) {
            $row->update($values);
        } else {
            $row = EmailTemplate::create($values + ['key' => $this->selectedKey, 'institute_id' => $this->instituteId(), 'created_by' => Auth::id()]);
        }

        $this->audit($old ? 'update' : 'create', 'email_templates', $row, [
            'old' => $old, 'new' => $row->only(['subject', 'body', 'button_text', 'button_url', 'status']),
            'description' => 'Saved email template "' . $this->templateName() . '" for ' . $this->scopeLabel(),
        ]);

        $this->loadTemplate();
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => 'Email template saved for ' . $this->scopeLabel() . '.']);
    }

    /** Delete this scope's version: the institute falls back to the group version, the group to the default. */
    public function resetToDefault()
    {
        $row = $this->ownRow();
        if ($row) {
            $this->audit('delete', 'email_templates', $row, [
                'old' => $row->only(['subject', 'body', 'button_text', 'button_url', 'status']),
                'description' => 'Reset email template "' . $this->templateName() . '" for ' . $this->scopeLabel(),
            ]);
            $row->delete();
        }

        $this->resetValidation();
        $this->loadTemplate();
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => $this->instituteId() ? 'Now using the group version.' : 'Now using the built-in default.']);
    }

    public function saveSettings()
    {
        abort_unless($this->instituteId(), 404);

        $this->validate([
            'senderName' => 'nullable|string|max:100',
            'replyTo' => 'nullable|email|max:150',
            'signature' => 'nullable|string|max:1000',
        ], [], ['senderName' => 'sender name', 'replyTo' => 'reply-to address']);

        $settings = InstituteEmailSetting::firstOrNew(['institute_id' => $this->instituteId()]);
        $old = $settings->exists ? $settings->only(['sender_name', 'reply_to', 'signature']) : null;
        $settings->fill([
            'sender_name' => trim($this->senderName) ?: null,
            'reply_to' => trim($this->replyTo) ?: null,
            'signature' => trim(str_replace("\r\n", "\n", $this->signature)) ?: null,
            'updated_by' => Auth::id(),
        ])->save();

        $this->audit('update', 'institute_email_settings', $settings, [
            'old' => $old, 'new' => $settings->only(['sender_name', 'reply_to', 'signature']),
            'description' => 'Updated email sender details for ' . $this->scopeLabel(),
        ]);

        $this->loadSettings();
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => 'Sender details saved.']);
    }

    /**
     * Send this template — as it is in the form, saved or not — with example values to an address, and
     * wait for SMTP2GO's answer. Works while sending is switched off.
     */
    public function sendTest()
    {
        $this->validate(['testEmail' => 'required|email'] + $this->rules(), [], ['testEmail' => 'test address'] + $this->validationAttributes);

        $emails = $this->emails();
        $user = Auth::user();
        $rendered = $emails->render($this->formTemplate(), $emails->sampleVars($this->selectedKey, $this->instituteId(), $this->sampleRecipient()), $this->instituteId());

        $log = $emails->sendCustom($user, '[Test] ' . $rendered['subject'], '', [
            'email' => $this->testEmail, 'institute_id' => $this->instituteId(), 'event_type' => 'template_test',
            'rendered' => $rendered, 'now' => true,
        ]);

        $this->testResult = $log->status === 'sent'
            ? ['ok' => true, 'message' => "Sent to {$this->testEmail} with example values."]
            : ['ok' => false, 'message' => $log->error ?: 'SMTP2GO did not accept the email.'];
    }

    // ------------------------------------------------------------------ View helpers

    protected function formTemplate(): array
    {
        return ['subject' => (string) $this->subject, 'body' => (string) $this->body, 'button_text' => $this->buttonText, 'button_url' => $this->buttonUrl];
    }

    /** Student emails preview with the example student; others are addressed to the signed-in user. */
    protected function sampleRecipient()
    {
        return ($this->emails()->catalogue()[$this->selectedKey]['audience'] ?? '') === 'student' ? null : Auth::user();
    }

    protected function templateName(): string
    {
        return $this->emails()->catalogue()[$this->selectedKey]['name'] ?? Str::headline($this->selectedKey);
    }

    protected function scopeLabel(): string
    {
        return $this->instituteId() ? (optional(Institute::find($this->instituteId()))->name ?? 'the institute') : 'all institutes';
    }

    public function render()
    {
        $emails = $this->emails();
        $catalogue = $this->catalogue();
        $instituteId = $this->instituteId();
        $isSuperAdmin = Auth::user()->isSuperAdmin();

        // Source of every template in this scope: institute / group / default, and on / off.
        $rows = EmailTemplate::whereIn('key', array_keys($catalogue))
            ->where(fn ($q) => $q->whereNull('institute_id')->when($instituteId, fn ($w) => $w->orWhere('institute_id', $instituteId)))
            ->get()->groupBy('key');
        $list = collect($catalogue)->map(function ($entry, $key) use ($rows, $instituteId) {
            $own = $rows->get($key, collect())->first(fn ($r) => $instituteId ? (int) $r->institute_id === $instituteId : $r->institute_id === null);
            $group = $rows->get($key, collect())->first(fn ($r) => $r->institute_id === null);
            $used = $own ?? ($instituteId ? $group : null);

            return $entry + [
                'key' => $key,
                'source' => $own ? ($instituteId ? 'institute' : 'group') : ($used ? 'group' : 'default'),
                'own' => (bool) $own,
                'on' => $used ? (bool) $used->status : true,
            ];
        })->groupBy('group');

        $current = $emails->template($this->selectedKey, $instituteId);
        $ownRow = $this->ownRow();
        $placeholders = $emails->placeholders($this->selectedKey);

        // Live preview of the form (saved or not) with example values
        $previewHtml = null;
        $unknown = [];
        try {
            $rendered = $emails->render($this->formTemplate(), $emails->sampleVars($this->selectedKey, $instituteId, $this->sampleRecipient()), $instituteId);
            $previewHtml = $rendered['html'];
            $previewSubject = $rendered['subject'];
            $previewFrom = $rendered['sender_name'];
            $previewReplyTo = $rendered['reply_to'];
        } catch (\Throwable $e) {
            report($e);
            $previewSubject = $previewFrom = $previewReplyTo = null;
        }
        preg_match_all('/\{([a-z0-9_]+)\}/i', $this->subject . ' ' . $this->body . ' ' . $this->buttonText . ' ' . $this->buttonUrl, $m);
        $unknown = array_values(array_diff(array_unique($m[1]), array_keys($placeholders)));

        return view('livewire.admin.settings.email-templates-component', [
            'isSuperAdmin' => $isSuperAdmin,
            'institutes' => $isSuperAdmin ? Institute::orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'scopeLabel' => $this->scopeLabel(),
            'groups' => config('email_templates.groups'),
            'list' => $list,
            'entry' => $catalogue[$this->selectedKey],
            'current' => $current,
            'hasOwn' => (bool) $ownRow,
            'ownRow' => $ownRow,
            'placeholders' => $placeholders,
            'unknown' => $unknown,
            'previewHtml' => $previewHtml,
            'previewSubject' => $previewSubject,
            'previewFrom' => $previewFrom,
            'previewReplyTo' => $previewReplyTo,
            'senderEmail' => (string) config('services.smtp2go.sender_email'),
            'emailLive' => $emails->enabled(),
        ])->layout('layouts.admin.master');
    }
}
