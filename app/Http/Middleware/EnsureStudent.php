<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admissions portal: only signed-in students with a student record. Staff are sent to the
 * admin panel; inactive students / institutes are signed out (same rules as the admin side).
 */
class EnsureStudent
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->guest(route('portal.login'));
        }

        if (!$user->hasRole('student')) {
            return redirect()->route('admin.dashboard');
        }

        if (($reason = EnsureUserIsActive::blockReason($user)) || !$user->student) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')->with('error', $reason ?? 'No application is linked to this account.');
        }

        return $next($request);
    }
}
