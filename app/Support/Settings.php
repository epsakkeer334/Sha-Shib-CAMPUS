<?php

namespace App\Support;

use App\Models\Admin\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * System Settings (Super Admin → Administration → System → System Settings).
 *
 * Values live in `system_settings` (JSON-encoded, the SMTP password encrypted) and are cached
 * forever under one key; saving clears the cache. Any key not saved yet uses DEFAULTS, so a fresh
 * install (or a missing table during deploy) behaves exactly as before the settings existed.
 */
class Settings
{
    public const CACHE_KEY = 'camp.system_settings';

    /** Keys stored encrypted. */
    public const SECRET = ['mail.password'];

    public const DEFAULTS = [
        // General
        'general.site_name' => 'Sha-Shib CAMPUS',
        'general.organisation' => 'Sha-Shib Group',
        'general.support_email' => '',
        'general.support_phone' => '',
        'general.address' => '',
        'general.footer_text' => '',

        // Appearance (admin panel)
        'theme.color' => 'primary',
        'theme.custom_color' => '#5B54D6',
        'theme.sidebar' => 'light',
        'theme.topbar' => 'white',
        'theme.mode' => 'light',
        'theme.allow_user' => false,
        'theme.loader' => true,

        // Email
        'mail.enabled' => false,
        'mail.host' => 'mail.smtp2go.com',
        'mail.port' => 2525,
        'mail.encryption' => 'tls',
        'mail.username' => '',
        'mail.password' => '',
        'mail.from_address' => '',
        'mail.from_name' => 'Sha-Shib CAMPUS',

        // Notifications (in-app bell)
        'notify.in_app' => true,
        'notify.staff' => true,
        'notify.student' => true,
        'notify.disabled_events' => [],

        // Admissions portal
        'portal.registration_open' => true,
        'portal.closed_message' => 'Admissions are closed at the moment. Please check back later or contact the admissions office.',

        // Security
        'security.session_lifetime' => 120,
    ];

    /** Primary colour presets of the admin template (data-color) → [label, hex]. */
    public const COLORS = [
        'primary' => ['Sha-Shib Orange', '#F26522'],
        'brightblue' => ['Bright Blue', '#4680FF'],
        'lavendar' => ['Royal Purple', '#673AB7'],
        'lunargreen' => ['Forest Green', '#365E32'],
        'magenta' => ['Magenta', '#E83E8C'],
        'chromeyellow' => ['Amber', '#FF9B04'],
        'lavared' => ['Crimson', '#DC2626'],
    ];

    /** Sidebar presets (data-sidebar) → [label, background]. */
    public const SIDEBARS = [
        'light' => ['Light', '#FFFFFF'],
        'dark' => ['Charcoal', '#111827'],
        'nightblue' => ['Night Blue', '#0F0F41'],
        'royalblue' => ['Royal Blue', '#0039C6'],
        'indigo' => ['Indigo', '#391694'],
        'darkgray' => ['Slate', '#2E2E48'],
        'darkgreen' => ['Deep Green', '#111926'],
    ];

    /** Top bar presets (data-topbar) → [label, background]. */
    public const TOPBARS = [
        'white' => ['White', '#FFFFFF'],
        'whiterock' => ['Sand', '#F0E4D7'],
        'bluehaze' => ['Blue Haze', '#B5C0D0'],
        'rockblue' => ['Teal Mist', '#8CB9BD'],
        'darkaqua' => ['Dark Aqua', '#116D6E'],
        'purplegradient' => ['Purple', 'linear-gradient(135deg, #4B3088, #5C2DC8)'],
        'bluegradient' => ['Ocean', 'linear-gradient(135deg, #00C0F9, #0257CE)'],
        'orangegradient' => ['Sunset', 'linear-gradient(135deg, #FF9945, #FC6173)'],
        'maroongradient' => ['Berry', 'linear-gradient(135deg, #EF3B4A, #513A8F)'],
    ];

    public static function get(string $key, $default = null)
    {
        $all = static::all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return array_key_exists($key, static::DEFAULTS) ? static::DEFAULTS[$key] : $default;
    }

    public static function bool(string $key): bool
    {
        return (bool) static::get($key);
    }

    /**
     * Saved values (decoded) merged over the defaults.
     */
    public static function all(): array
    {
        // Kept per request in the container (not a static), so tests and queue workers never see stale values.
        if (app()->bound('camp.settings')) {
            return app('camp.settings');
        }

        try {
            $saved = Cache::rememberForever(static::CACHE_KEY, fn () => SystemSetting::pluck('value', 'key')->all());
        } catch (\Throwable $e) {
            // Table missing (deploy before migrate) or DB down: run on the defaults.
            $saved = [];
        }

        $values = static::DEFAULTS;
        foreach ($saved as $key => $raw) {
            $values[$key] = static::decode($key, $raw);
        }

        app()->instance('camp.settings', $values);

        return $values;
    }

    /**
     * Save several settings at once. Returns [key => [old, new]] of what changed (secrets masked).
     */
    public static function set(array $values): array
    {
        $current = static::all();
        $changes = [];

        foreach ($values as $key => $value) {
            if (!array_key_exists($key, static::DEFAULTS)) {
                continue;
            }

            $old = $current[$key] ?? null;
            if ($old === $value) {
                continue;
            }

            SystemSetting::updateOrCreate(['key' => $key], ['value' => static::encode($key, $value), 'updated_by' => Auth::id()]);

            $secret = in_array($key, static::SECRET, true);
            $changes[$key] = [$secret ? '••••' : $old, $secret ? '••••' : $value];
        }

        static::flush();

        return $changes;
    }

    public static function flush(): void
    {
        app()->forgetInstance('camp.settings');
        try {
            Cache::forget(static::CACHE_KEY);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected static function encode(string $key, $value): string
    {
        $json = json_encode($value);

        return in_array($key, static::SECRET, true) && $value !== '' ? Crypt::encryptString($json) : $json;
    }

    protected static function decode(string $key, $raw)
    {
        if ($raw === null) {
            return static::DEFAULTS[$key] ?? null;
        }

        if (in_array($key, static::SECRET, true) && !str_starts_with($raw, '"') && $raw !== 'null') {
            try {
                $raw = Crypt::decryptString($raw);
            } catch (\Throwable $e) {
                return ''; // APP_KEY changed: the password has to be entered again
            }
        }

        $value = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $value : $raw;
    }

    // ------------------------------------------------------------------ Runtime

    /**
     * Push settings into Laravel config at boot: SMTP mailer, session lifetime, app name.
     */
    public static function applyRuntimeConfig(): void
    {
        $s = static::all();

        config(['session.lifetime' => max(5, (int) $s['security.session_lifetime'])]);

        if (static::mailConfigured()) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $s['mail.host'],
                'mail.mailers.smtp.port' => (int) $s['mail.port'],
                'mail.mailers.smtp.encryption' => $s['mail.encryption'] === 'none' ? null : $s['mail.encryption'],
                'mail.mailers.smtp.username' => $s['mail.username'] ?: null,
                'mail.mailers.smtp.password' => $s['mail.password'] ?: null,
                'mail.from.address' => $s['mail.from_address'],
                'mail.from.name' => $s['mail.from_name'] ?: $s['general.site_name'],
            ]);
        }
    }

    /**
     * Drop the already-built SMTP mailer so the next email uses the current config
     * (no-op under Mail::fake(), whose fake has no purge()).
     */
    public static function refreshMailer(): void
    {
        $manager = app('mail.manager');
        if (method_exists($manager, 'purge')) {
            $manager->purge('smtp');
        }
    }

    /** SMTP details are complete enough to try sending. */
    public static function mailConfigured(): bool
    {
        return static::get('mail.host') !== '' && static::get('mail.from_address') !== '' && (int) static::get('mail.port') > 0;
    }

    /** Emails are actually sent (switch on + SMTP filled in). */
    public static function emailEnabled(): bool
    {
        return static::bool('mail.enabled') && static::mailConfigured();
    }

    /**
     * Whether an in-app workflow notification may go to this audience ('staff' | 'student').
     */
    public static function notificationAllowed(string $event, string $audience): bool
    {
        if (!static::bool('notify.in_app') || !static::bool('notify.' . $audience)) {
            return false;
        }

        return !in_array($event, (array) static::get('notify.disabled_events', []), true);
    }

    /**
     * Theme of the admin panel, for the layout script (see layouts/admin/master.blade.php).
     */
    public static function theme(): array
    {
        $color = static::get('theme.color');
        $custom = static::get('theme.custom_color');

        return [
            'color' => $color === 'custom' ? null : (array_key_exists($color, static::COLORS) ? $color : 'primary'),
            'rgb' => $color === 'custom' ? static::hexToRgb($custom) : null,
            'hex' => $color === 'custom' ? $custom : (static::COLORS[$color][1] ?? '#F26522'),
            'sidebar' => array_key_exists(static::get('theme.sidebar'), static::SIDEBARS) ? static::get('theme.sidebar') : 'light',
            'topbar' => array_key_exists(static::get('theme.topbar'), static::TOPBARS) ? static::get('theme.topbar') : 'white',
            'mode' => static::get('theme.mode') === 'dark' ? 'dark' : 'light',
            'loader' => static::bool('theme.loader') ? 'enable' : 'disable',
            'allowUser' => static::bool('theme.allow_user'),
            // Changes whenever the Super Admin saves a new theme, so it is re-applied once for everyone.
            'version' => substr(md5(json_encode([$color, $custom, static::get('theme.sidebar'), static::get('theme.topbar'), static::get('theme.mode'), static::get('theme.loader')])), 0, 10),
        ];
    }

    public static function hexToRgb(?string $hex): string
    {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return '242, 101, 34';
        }

        return implode(', ', array_map('hexdec', str_split($hex, 2)));
    }
}
