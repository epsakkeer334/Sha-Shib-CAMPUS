<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admissions portal pages use the `student` guard, the admin panel keeps `web`.
 *
 * Both guards keep their login in the same session under different keys, so a staff member signed in to
 * the admin panel can open the portal in the same browser and see it as a visitor (start an application,
 * sign in as a student …) without being sent to the admin panel or signed out of it.
 *
 * Registered as Livewire persistent middleware, so button clicks on portal pages use the same guard.
 */
class UseStudentGuard
{
    public const GUARD = 'student';

    public function handle(Request $request, Closure $next)
    {
        Auth::shouldUse(static::GUARD);

        return $next($request);
    }

    /**
     * Sign out of the current guard only. The whole session is reset only when nobody is signed in on the
     * other side; otherwise the session id is renewed and the other login is kept.
     */
    public static function signOut(Request $request): void
    {
        $current = Auth::getDefaultDriver();
        Auth::guard($current)->logout();

        $other = $current === static::GUARD ? 'web' : static::GUARD;
        if (Auth::guard($other)->check()) {
            $request->session()->migrate(true);
        } else {
            $request->session()->invalidate();
        }
        $request->session()->regenerateToken();
    }
}
