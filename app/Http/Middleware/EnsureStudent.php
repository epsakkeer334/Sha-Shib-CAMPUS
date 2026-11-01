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

        // Only students can hold the portal login; anything else is signed out of the portal (not of the admin panel).
        if (!$user->hasRole('student')) {
            UseStudentGuard::signOut($request);

            return redirect()->guest(route('portal.login'));
        }

        if (($reason = EnsureUserIsActive::blockReason($user)) || !$user->student) {
            UseStudentGuard::signOut($request);

            return redirect()->route('portal.login')->with('error', $reason ?? 'No application is linked to this account.');
        }

        return $next($request);
    }
}
