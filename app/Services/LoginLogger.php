<?php

namespace App\Services;

use App\Models\Admin\LoginLog;
use App\Models\User;
use Jenssegers\Agent\Agent;

/**
 * Writes Login Activity rows (Monitoring ▸ Login Activity) for the admin panel and the admissions portal.
 *
 * Logging never gets in the way of signing in: any error here is reported and ignored.
 */
class LoginLogger
{
    public static function login(?User $user, string $panel, ?string $reason = null): void
    {
        static::record('login', $user, $panel, $user ? $user->email : null, $reason);
    }

    /** Wrong password, or no account with this email. */
    public static function failed(string $email, string $panel): void
    {
        $user = User::where('email', $email)->first();
        static::record('failed', $user, $panel, $email, $user ? 'Wrong password' : 'No account with this email');
    }

    /** Correct password, but the account may not sign in here (inactive, wrong panel, too many attempts …). */
    public static function blocked(?User $user, string $panel, string $reason, ?string $email = null): void
    {
        static::record('blocked', $user, $panel, $email ?? ($user ? $user->email : null), $reason);
    }

    public static function logout(?User $user, string $panel, ?string $reason = null): void
    {
        if ($user) {
            static::record('logout', $user, $panel, $user->email, $reason);
        }
    }

    protected static function record(string $event, ?User $user, string $panel, ?string $email, ?string $reason): void
    {
        try {
            $request = request();
            $agentString = (string) $request->userAgent();
            $agent = new Agent(null, $agentString);

            LoginLog::create([
                'user_id' => $user ? $user->id : null,
                'institute_id' => $user ? $user->institute_id : null,
                'email' => $email ? mb_substr(trim($email), 0, 191) : null,
                'panel' => $panel,
                'event' => $event,
                'reason' => $reason ? mb_substr($reason, 0, 255) : null,
                'ip' => $request->ip(),
                'user_agent' => $agentString !== '' ? mb_substr($agentString, 0, 500) : null,
                'browser' => mb_substr((string) $agent->browser(), 0, 60) ?: null,
                'platform' => mb_substr((string) $agent->platform(), 0, 60) ?: null,
                'device' => $agent->isRobot() ? 'bot' : ($agent->isTablet() ? 'tablet' : ($agent->isMobile() ? 'mobile' : ($agentString !== '' ? 'desktop' : null))),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
