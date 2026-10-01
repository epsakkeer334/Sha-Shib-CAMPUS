<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logs out users who were deactivated, or whose institute was deactivated/deleted,
 * after they signed in.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && ($reason = static::blockReason($user))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', $reason);
        }

        return $next($request);
    }

    /**
     * Why this user may not use the system, or null when they may.
     */
    public static function blockReason($user): ?string
    {
        if (!$user->status) {
            return 'Your account is inactive. Please contact the administrator.';
        }

        if ($user->roles->isEmpty()) {
            return 'No role is assigned to your account. Please contact the administrator.';
        }

        if (!$user->isSuperAdmin() && (!$user->institute || !$user->institute->status)) {
            return 'Your institute is inactive. Please contact the administrator.';
        }

        return null;
    }
}
