<?php

namespace App\Http\Livewire\Admin\Settings;

use App\Support\Settings;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Super Admin → Administration → System → System Settings.
 * Tabs: General, Appearance, Email, Notifications, Admissions, System. Each tab saves on its own
 * and every change is written to the audit trail (the SMTP password is masked).
 */
class SystemSettingsComponent extends Component
{
    use RecordsAuditTrail;

    public const TABS = [
        'general' => ['General', 'ti ti-building', 'Organisation name and contact details'],
        'appearance' => ['Appearance', 'ti ti-palette', 'Theme colour, sidebar and top bar'],
        'email' => ['Email', 'ti ti-mail', 'Sending on / off and SMTP server'],
        'notifications' => ['Notifications', 'ti ti-bell', 'In-app alerts for staff and students'],
        'admissions' => ['Admissions', 'ti ti-school', 'Online registration on the portal'],
        'system' => ['System', 'ti ti-server', 'Session, server health and cache'],
    ];

    /** Setting keys saved by each tab. */
    protected const GROUPS = [
        'general' => ['general.site_name', 'general.organisation', 'general.support_email', 'general.support_phone', 'general.address', 'general.footer_text'],
        'appearance' => ['theme.color', 'theme.custom_color', 'theme.sidebar', 'theme.topbar', 'theme.mode', 'theme.allow_user', 'theme.loader'],
        'email' => ['mail.enabled', 'mail.host', 'mail.port', 'mail.encryption', 'mail.username', 'mail.from_address', 'mail.from_name'],
        'notifications' => ['notify.in_app', 'notify.staff', 'notify.student'],
        'admissions' => ['portal.registration_open', 'portal.closed_message'],
        'system' => ['security.session_lifetime'],
    ];

    public string $tab = 'general';

    /** Form values, nested by group: $s['general']['site_name'] ↔ setting general.site_name */
    public array $s = [];

    /** event key → enabled (in-app notifications) */
    public array $events = [];

    /** New SMTP password; empty keeps the saved one. */
    public string $mailPassword = '';
    public bool $passwordSaved = false;

    public string $testEmail = '';
    public ?array $testResult = null;

    protected $queryString = ['tab' => ['except' => 'general']];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);

        if (!array_key_exists($this->tab, static::TABS)) {
            $this->tab = 'general';
        }

        $this->loadForm();
        $this->testEmail = (string) auth()->user()->email;
    }

    protected function loadForm(): void
    {
        $all = Settings::all();
        $this->s = [];
        foreach (static::GROUPS as $keys) {
            foreach ($keys as $key) {
                data_set($this->s, $key, $all[$key]);
            }
        }

        $disabled = (array) $all['notify.disabled_events'];
        $this->events = collect(array_keys(config('workflow_notifications.events', [])))
            ->mapWithKeys(fn ($event) => [$event => !in_array($event, $disabled, true)])->all();

        $this->passwordSaved = $all['mail.password'] !== '';
        $this->mailPassword = '';
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, static::TABS)) {
            $this->tab = $tab;
            $this->resetValidation();
        }
    }

    // ------------------------------------------------------------------ Save

    protected function rules(): array
    {
        return [
            'general' => [
                's.general.site_name' => 'required|string|max:80',
                's.general.organisation' => 'required|string|max:120',
                's.general.support_email' => 'nullable|email|max:150',
                's.general.support_phone' => 'nullable|string|max:30',
                's.general.address' => 'nullable|string|max:500',
                's.general.footer_text' => 'nullable|string|max:200',
            ],
            'appearance' => [
                's.theme.color' => 'required|in:custom,' . implode(',', array_keys(Settings::COLORS)),
                's.theme.custom_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                's.theme.sidebar' => 'required|in:' . implode(',', array_keys(Settings::SIDEBARS)),
                's.theme.topbar' => 'required|in:' . implode(',', array_keys(Settings::TOPBARS)),
                's.theme.mode' => 'required|in:light,dark',
                's.theme.allow_user' => 'boolean',
                's.theme.loader' => 'boolean',
            ],
            'email' => [
                's.mail.enabled' => 'boolean',
                's.mail.host' => 'required_if:s.mail.enabled,true|nullable|string|max:150',
                's.mail.port' => 'required|integer|between:1,65535',
                's.mail.encryption' => 'required|in:tls,ssl,none',
                's.mail.username' => 'nullable|string|max:150',
                's.mail.from_address' => 'required_if:s.mail.enabled,true|nullable|email|max:150',
                's.mail.from_name' => 'nullable|string|max:100',
                'mailPassword' => 'nullable|string|max:200',
            ],
            'notifications' => [
                's.notify.in_app' => 'boolean',
                's.notify.staff' => 'boolean',
                's.notify.student' => 'boolean',
                'events.*' => 'boolean',
            ],
            'admissions' => [
                's.portal.registration_open' => 'boolean',
                's.portal.closed_message' => 'required|string|max:500',
            ],
            'system' => [
                's.security.session_lifetime' => 'required|integer|between:5,1440',
            ],
        ][$this->tab] ?? [];
    }

    protected function validationAttributes(): array
    {
        return [
            's.general.site_name' => 'system name', 's.general.organisation' => 'organisation', 's.general.support_email' => 'support email',
            's.theme.custom_color' => 'custom colour', 's.mail.host' => 'SMTP host', 's.mail.port' => 'port',
            's.mail.from_address' => 'from address', 's.portal.closed_message' => 'closed message', 's.security.session_lifetime' => 'session timeout',
        ];
    }

    protected function messages(): array
    {
        return [
            's.mail.host.required_if' => 'Enter the SMTP host to switch email sending on.',
            's.mail.from_address.required_if' => 'Enter the from address to switch email sending on.',
        ];
    }

    public function save(): void
    {
        $this->validate($this->rules());

        $values = [];
        foreach (static::GROUPS[$this->tab] as $key) {
            $value = data_get($this->s, $key);
            $default = Settings::DEFAULTS[$key];
            $values[$key] = match (true) {
                is_bool($default) => (bool) $value,
                is_int($default) => (int) $value,
                default => is_string($value) ? trim($value) : $value,
            };
        }

        if ($this->tab === 'email' && $this->mailPassword !== '') {
            $values['mail.password'] = $this->mailPassword;
        }
        if ($this->tab === 'notifications') {
            $values['notify.disabled_events'] = array_values(array_keys(array_filter($this->events, fn ($on) => !$on)));
        }
        if ($this->tab === 'appearance') {
            $values['theme.custom_color'] = strtoupper($values['theme.custom_color']);
        }

        $changes = Settings::set($values);

        if ($changes) {
            $this->audit('update', 'System Settings', null, [
                'old' => array_map(fn ($c) => $c[0], $changes),
                'new' => array_map(fn ($c) => $c[1], $changes),
                'description' => 'Updated ' . static::TABS[$this->tab][0] . ' settings',
            ]);
        }

        $this->loadForm();

        $message = $changes ? static::TABS[$this->tab][0] . ' settings saved.' : 'Nothing changed.';
        if ($this->tab === 'appearance' && $changes) {
            $this->dispatchBrowserEvent('camp-theme-saved');
            $message = 'Theme saved — applying it now.';
        }

        $this->dispatchBrowserEvent('show-toast', ['type' => $changes ? 'success' : 'info', 'message' => $message]);
    }

    public function pickColor(string $color): void
    {
        $this->s['theme']['color'] = $color;
    }

    /** Common SMTP providers → [label, host, port, encryption]. GoDaddy VPS blocks port 25, so none use it. */
    public const SMTP_PRESETS = [
        'smtp2go' => ['SMTP2GO', 'mail.smtp2go.com', 2525, 'tls'],
        'godaddy' => ['GoDaddy Email', 'smtpout.secureserver.net', 465, 'ssl'],
        'gmail' => ['Gmail / Workspace', 'smtp.gmail.com', 587, 'tls'],
        'office365' => ['Microsoft 365', 'smtp.office365.com', 587, 'tls'],
    ];

    public function applyPreset(string $preset): void
    {
        if ($p = static::SMTP_PRESETS[$preset] ?? null) {
            [, $this->s['mail']['host'], $this->s['mail']['port'], $this->s['mail']['encryption']] = $p;
        }
    }

    public function removePassword(): void
    {
        Settings::set(['mail.password' => '']);
        $this->audit('update', 'System Settings', null, ['description' => 'Removed the SMTP password']);
        $this->loadForm();
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => 'SMTP password removed.']);
    }

    // ------------------------------------------------------------------ Email test

    /**
     * Sends a test email with the details currently in the form (saved or not).
     */
    public function sendTest(): void
    {
        $this->validate([
            'testEmail' => 'required|email',
            's.mail.host' => 'required|string',
            's.mail.port' => 'required|integer|between:1,65535',
            's.mail.from_address' => 'required|email',
        ], [], ['testEmail' => 'test address', 's.mail.host' => 'SMTP host', 's.mail.from_address' => 'from address']);

        $m = $this->s['mail'];
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $m['host'],
            'mail.mailers.smtp.port' => (int) $m['port'],
            'mail.mailers.smtp.encryption' => $m['encryption'] === 'none' ? null : $m['encryption'],
            'mail.mailers.smtp.username' => $m['username'] ?: null,
            'mail.mailers.smtp.password' => $this->mailPassword !== '' ? $this->mailPassword : (Settings::get('mail.password') ?: null),
            'mail.mailers.smtp.timeout' => 15,
            'mail.from.address' => $m['from_address'],
            'mail.from.name' => $m['from_name'] ?: $this->s['general']['site_name'] ?? 'CAMPUS',
        ]);
        Settings::refreshMailer(); // rebuild the SMTP mailer with the details above

        try {
            $site = Settings::get('general.site_name');
            Mail::raw(
                "This is a test email from {$site}.\n\nIf you can read this, the SMTP settings work. Sent " . now()->format('d M Y, h:i A') . '.',
                fn ($mail) => $mail->to($this->testEmail)->subject("{$site} — test email")
            );
            $this->testResult = ['ok' => true, 'message' => "Test email sent to {$this->testEmail}. Check the inbox (and spam folder)."];
        } catch (\Throwable $e) {
            $this->testResult = ['ok' => false, 'message' => Str::limit($e->getMessage(), 400)];
        }

        $this->audit('update', 'System Settings', null, ['description' => 'Sent a test email to ' . $this->testEmail . ($this->testResult['ok'] ? '' : ' (failed)')]);
    }

    // ------------------------------------------------------------------ System

    public function clearCache(): void
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            Settings::flush();
            $this->audit('update', 'System Settings', null, ['description' => 'Cleared the application and view cache']);
            $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => 'Application and view cache cleared.']);
        } catch (\Throwable $e) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'danger', 'message' => 'Could not clear the cache: ' . Str::limit($e->getMessage(), 120)]);
        }
    }

    protected function systemInfo(): array
    {
        $dbVersion = '—';
        try {
            $dbVersion = DB::selectOne('select version() as v')->v ?? '—';
        } catch (\Throwable $e) {
        }

        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        $storageWritable = is_writable(storage_path('app')) && is_writable(storage_path('logs')) && is_writable(storage_path('framework/cache'));
        $cacheWritable = is_writable(base_path('bootstrap/cache'));

        return [
            'env' => [
                ['Environment', app()->environment(), app()->environment('production') ? 'ok' : 'warn', app()->environment('production') ? null : 'Set APP_ENV=production on the live server'],
                ['Debug mode', config('app.debug') ? 'On' : 'Off', config('app.debug') ? 'bad' : 'ok', config('app.debug') ? 'Turn off on the live server: APP_DEBUG=false' : null],
                ['Site URL', config('app.url'), Str::startsWith(config('app.url'), 'https://') ? 'ok' : 'warn', Str::startsWith(config('app.url'), 'https://') ? null : 'Use https:// in APP_URL'],
                ['Timezone', config('app.timezone'), 'info', null],
            ],
            'server' => [
                ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.0.2', '>=') ? 'ok' : 'bad', null],
                ['Laravel', app()->version(), 'info', null],
                ['Database', DB::connection()->getDriverName() . ' ' . $dbVersion, 'info', null],
                ['Web server', Str::limit((string) ($_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name()), 40), 'info', null],
                ['Upload limit', ini_get('upload_max_filesize') . ' (post ' . ini_get('post_max_size') . ')', 'info', null],
                ['Memory limit', ini_get('memory_limit'), 'info', null],
            ],
            'app' => [
                ['Cache driver', config('cache.default'), 'info', null],
                ['Session driver', config('session.driver') . ' · ' . config('session.lifetime') . ' min', 'info', null],
                ['Queue', config('queue.default'), 'info', null],
                ['Storage folder', $storageWritable ? 'Writable' : 'Not writable', $storageWritable ? 'ok' : 'bad', $storageWritable ? null : 'chown -R www-data storage bootstrap/cache'],
                ['bootstrap/cache', $cacheWritable ? 'Writable' : 'Not writable', $cacheWritable ? 'ok' : 'bad', null],
                ['Public storage link', File::exists(public_path('storage')) ? 'Linked' : 'Missing', File::exists(public_path('storage')) ? 'ok' : 'warn', File::exists(public_path('storage')) ? null : 'Run: php artisan storage:link'],
            ],
            'disk' => $total ? ['free' => $free, 'total' => $total, 'used_pct' => round(100 - $free / $total * 100)] : null,
        ];
    }

    public function render()
    {
        return view('livewire.admin.settings.system-settings-component', [
            'tabs' => static::TABS,
            'theme' => Settings::theme(),
            'eventGroups' => collect(config('workflow_notifications.events', []))
                ->map(fn ($e, $key) => $e + ['key' => $key])
                ->groupBy('stage'),
            'stages' => config('workflow_notifications.stages', []),
            'emailLive' => Settings::emailEnabled(),
            'info' => $this->tab === 'system' ? $this->systemInfo() : null,
        ])->layout('layouts.admin.master');
    }
}
