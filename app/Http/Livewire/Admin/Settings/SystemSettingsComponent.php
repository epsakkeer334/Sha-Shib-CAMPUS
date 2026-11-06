<?php

namespace App\Http\Livewire\Admin\Settings;

use App\Models\Admin\SystemSetting;
use App\Models\User;
use App\Services\EmailService;
use App\Support\ArtisanRunner;
use App\Support\BackgroundJobs;
use App\Support\PhpCliFinder;
use App\Support\Settings;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Super Admin → Administration → System → System Settings.
 * Tabs: General, Appearance, Email, Background Jobs, Notifications, Admissions, System. Each tab saves on its own
 * and every change is written to the audit trail. Email goes out through SMTP2GO (key and sender in .env).
 */
class SystemSettingsComponent extends Component
{
    use RecordsAuditTrail;

    public const TABS = [
        'general' => ['General', 'ti ti-building', 'Organisation name and contact details'],
        'appearance' => ['Appearance', 'ti ti-palette', 'Theme colour, sidebar and top bar'],
        'email' => ['Email', 'ti ti-mail', 'Sending on / off, SMTP2GO and test email'],
        'jobs' => ['Background Jobs', 'ti ti-server-bolt', 'Queue worker, scheduler and check-ins'],
        'notifications' => ['Notifications', 'ti ti-bell', 'In-app alerts for staff and students'],
        'admissions' => ['Admissions', 'ti ti-school', 'Online registration on the portal'],
        'system' => ['System', 'ti ti-server', 'Session, server health and cache'],
    ];

    /** Setting keys saved by each tab. */
    protected const GROUPS = [
        'general' => ['general.site_name', 'general.organisation', 'general.support_email', 'general.support_phone', 'general.address', 'general.footer_text'],
        'appearance' => ['theme.color', 'theme.custom_color', 'theme.sidebar', 'theme.topbar', 'theme.mode', 'theme.allow_user', 'theme.loader'],
        'email' => ['mail.enabled'],
        'jobs' => ['queue.worker_enabled'],
        'notifications' => ['notify.in_app', 'notify.staff', 'notify.student'],
        'admissions' => ['portal.registration_open', 'portal.closed_message'],
        'system' => ['security.session_lifetime'],
    ];

    public string $tab = 'general';

    /** Form values, nested by group: $s['general']['site_name'] ↔ setting general.site_name */
    public array $s = [];

    /** event key → enabled (in-app notifications) */
    public array $events = [];

    public string $testEmail = '';
    public ?array $testResult = null;

    /** Output of the last cache command run from the System tab. */
    public ?array $artisanResult = null;

    protected $queryString = ['tab' => ['except' => 'general']];

    /** Every button click (Livewire request) is checked again, not only the first page load. */
    public function hydrate(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
    }

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
                's.mail.enabled' => ['boolean', function ($attribute, $value, $fail) {
                    if ($value && !Settings::mailConfigured()) {
                        $fail('Add SMTP2GO_API_KEY and SMTP2GO_SENDER_EMAIL to the .env file before switching email on.');
                    }
                }],
            ],
            'jobs' => [
                's.queue.worker_enabled' => 'boolean',
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
            's.theme.custom_color' => 'custom colour', 's.portal.closed_message' => 'closed message', 's.security.session_lifetime' => 'session timeout',
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

    // ------------------------------------------------------------------ Email test

    /**
     * Sends the "Test email" template through SMTP2GO and waits for the answer (the one email that is not
     * sent in the background — the Super Admin wants the result). Works while sending is switched off.
     */
    public function sendTest(EmailService $emails): void
    {
        $this->validate(['testEmail' => 'required|email'], [], ['testEmail' => 'test address']);

        $log = $emails->sendTemplate('test_email', auth()->user(), ['sent_at' => now()->format('d M Y, h:i A')], [
            'email' => $this->testEmail, 'institute_id' => null, 'now' => true, 'force' => true,
        ]);

        $this->testResult = $log->status === 'sent'
            ? ['ok' => true, 'message' => "Test email sent to {$this->testEmail} (SMTP2GO id {$log->provider_message_id}). Check the inbox and spam folder."]
            : ['ok' => false, 'message' => $log->error ?: 'SMTP2GO did not accept the email.'];

        $this->audit('update', 'System Settings', null, ['description' => 'Sent a test email to ' . $this->testEmail . ($this->testResult['ok'] ? '' : ' (failed)')]);
    }

    // ------------------------------------------------------------------ Background jobs

    /**
     * Sends the emails waiting on the "emails" queue now, inside this request (the same worker the scheduler
     * runs each minute). For when the scheduler is not running yet, or to clear the queue at once.
     */
    public function runWaiting(): void
    {
        $before = BackgroundJobs::waiting();
        if ($before === 0) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'info', 'message' => 'No emails are waiting in the queue.']);

            return;
        }

        BackgroundJobs::$manualRun = true;
        try {
            Artisan::call('queue:work', [
                'connection' => BackgroundJobs::CONNECTION, '--queue' => BackgroundJobs::QUEUE,
                '--stop-when-empty' => true, '--max-jobs' => 100, '--max-time' => 25, '--tries' => 3,
            ]);
        } catch (\Throwable $e) {
            report($e);
        } finally {
            BackgroundJobs::$manualRun = false;
        }

        $left = BackgroundJobs::waiting();
        $done = max(0, $before - $left);
        $this->audit('update', 'System Settings', null, ['description' => "Ran the email queue now ({$done} of {$before} processed)"]);
        $this->dispatchBrowserEvent('show-toast', [
            'type' => $left ? 'warning' : 'success',
            'message' => "{$done} queued email" . ($done === 1 ? '' : 's') . ' processed' . ($left ? " — {$left} still waiting (retry later)." : '.'),
        ]);
    }

    protected function jobsInfo(): array
    {
        return [
            'mode' => BackgroundJobs::mode(),
            'mode_label' => BackgroundJobs::modeLabel(),
            'scheduler' => BackgroundJobs::status(BackgroundJobs::SCHEDULER),
            'worker' => BackgroundJobs::status(BackgroundJobs::WORKER),
            'queue' => BackgroundJobs::queueStatus(),
            'cron' => BackgroundJobs::cronCommand(),
            'stale' => BackgroundJobs::STALE_AFTER,
        ];
    }

    // ------------------------------------------------------------------ System

    public function clearCache(): void
    {
        $this->runArtisan(['cache:clear', 'view:clear'], 'Cleared the application and view cache', 'Application and view cache cleared.');
    }

    /** Same as `php artisan optimize:clear`: config, route, view, event, compiled and application cache. */
    public function optimizeClear(): void
    {
        $this->runArtisan(['optimize:clear'], 'Cleared all caches (optimize:clear)', 'All caches cleared.');
    }

    /**
     * Same as `php artisan optimize:clear && php artisan optimize` (config + route cache).
     * Not view:cache — the unused Breeze views in resources/views/auth reference components that don't exist; views compile on first use.
     */
    public function optimizeApp(): void
    {
        $this->runArtisan(['optimize:clear', 'optimize'],'Cleared and rebuilt caches (optimize)', 'Caches cleared and rebuilt — the site is optimised.');
    }

    /** Runs the commands in order, stops at the first failure and shows their output in the Cache card. */
    protected function runArtisan(array $commands, string $auditText, string $successText): void
    {
        $lines = [];
        $ok = true;
        foreach ($commands as $command) {
            $lines[] = '$ php artisan ' . $command;
            try {
                [$code, $out] = app(ArtisanRunner::class)->run($command);
                $lines[] = $out !== '' ? $out : ($code === 0 ? 'Done.' : '');
                if ($code !== 0) {
                    $ok = false;
                    $lines[] = "Stopped: the command ended with code {$code}.";
                    break;
                }
            } catch (\Throwable $e) {
                $ok = false;
                $lines[] = 'Error: ' . Str::limit($e->getMessage(), 300);
                break;
            }
        }
        Settings::flush();

        $this->artisanResult = ['ok' => $ok, 'output' => implode("\n", $lines)];
        $this->audit('update', 'System Settings', null, ['description' => $auditText . ($ok ? '' : ' (failed)')]);
        $this->dispatchBrowserEvent('show-toast', $ok
            ? ['type' => 'success', 'message' => $successText]
            : ['type' => 'danger', 'message' => 'The command failed — see the output in the Cache card.']);
    }

    protected function cacheStatus(): array
    {
        $views = glob(rtrim((string) config('view.compiled'), '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];

        return [
            ['Config', app()->configurationIsCached()],
            ['Routes', app()->routesAreCached()],
            ['Events', app()->eventsAreCached()],
            ['Views', count($views) > 0, count($views) . ' compiled'],
        ];
    }

    /** Server health row: which PHP CLI the Optimise button will use. */
    protected function phpCliRow(): array
    {
        if (!PhpCliFinder::processesAllowed()) {
            return ['PHP CLI (Optimise)', 'proc_open disabled', 'bad', 'Remove proc_open from disable_functions, or optimise over SSH'];
        }
        $php = app(PhpCliFinder::class)->find();
        if (!$php['binary']) {
            return ['PHP CLI (Optimise)', 'Not found', 'bad', 'Set PHP_CLI_BINARY=/opt/plesk/php/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '/bin/php in .env'];
        }
        $same = str_starts_with($php['version'], PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.');

        return ['PHP CLI (Optimise)', "PHP {$php['version']} · {$php['binary']}", $same ? 'ok' : 'warn',
            $same ? null : 'Different version from the website (' . PHP_VERSION . '); set PHP_CLI_BINARY to match'];
    }

    /** "Last change 2 hours ago by …" in the page header. */
    protected function lastChange(): ?array
    {
        try {
            $row = SystemSetting::query()->latest('updated_at')->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? ['at' => $row->updated_at, 'by' => optional(User::find($row->updated_by))->name] : null;
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
                ['PHP', PHP_VERSION . ' · ' . PHP_SAPI, version_compare(PHP_VERSION, '8.0.2', '>=') ? 'ok' : 'bad', null],
                $this->phpCliRow(),
                ['Laravel', app()->version(), 'info', null],
                ['Database', DB::connection()->getDriverName() . ' ' . $dbVersion, 'info', null],
                ['Web server', Str::limit((string) ($_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name()), 40), 'info', null],
                ['Upload limit', ini_get('upload_max_filesize') . ' (post ' . ini_get('post_max_size') . ')', 'info', null],
                ['Memory limit', ini_get('memory_limit'), 'info', null],
            ],
            'app' => [
                ['Cache driver', config('cache.default'), 'info', null],
                ['Session driver', config('session.driver') . ' · ' . config('session.lifetime') . ' min', 'info', null],
                ['Email delivery', ['worker' => 'Queue worker', 'fallback' => 'After the page (worker fallback)', 'direct' => 'After the page'][BackgroundJobs::mode()],
                    BackgroundJobs::mode() === 'fallback' ? 'warn' : 'info', BackgroundJobs::mode() === 'fallback' ? 'The scheduler is not running — see Background Jobs' : null],
                ['Storage folder', $storageWritable ? 'Writable' : 'Not writable', $storageWritable ? 'ok' : 'bad', $storageWritable ? null : 'chown -R www-data storage bootstrap/cache'],
                ['bootstrap/cache', $cacheWritable ? 'Writable' : 'Not writable', $cacheWritable ? 'ok' : 'bad', null],
                ['Public storage link', File::exists(public_path('storage')) ? 'Linked' : 'Missing', File::exists(public_path('storage')) ? 'ok' : 'warn', File::exists(public_path('storage')) ? null : 'Run: php artisan storage:link'],
            ],
            'cache' => $this->cacheStatus(),
            'disk' => $total ?['free' => $free, 'total' => $total, 'used_pct' => round(100 - $free / $total * 100)] : null,
        ];
    }

    /** SMTP2GO details from .env for the Email tab (the API key only masked), plus today's sending figures. */
    protected function smtp2goInfo(): array
    {
        $client = app(\App\Services\Email\Smtp2GoClient::class);
        $today = \App\Models\Admin\NotificationLog::where('channel', 'email')->where('created_at', '>=', today())
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'configured' => $client->configured(),
            'key' => $client->maskedKey(),
            'sender_email' => $client->senderEmail(),
            'sender_name' => $client->defaultSenderName(),
            'background' => BackgroundJobs::modeLabel(),
            'today' => ['sent' => (int) ($today['sent'] ?? 0), 'pending' => (int) ($today['pending'] ?? 0), 'failed' => (int) ($today['failed'] ?? 0)],
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
            'smtp2go' => $this->tab === 'email' ? $this->smtp2goInfo() : null,
            'jobs' => $this->tab === 'jobs' ? $this->jobsInfo() : null,
            'workerMode' => BackgroundJobs::mode(),
            'info' => $this->tab === 'system' ? $this->systemInfo() : null,
            'lastChange' => $this->lastChange(),
        ])->layout('layouts.admin.master');
    }
}
