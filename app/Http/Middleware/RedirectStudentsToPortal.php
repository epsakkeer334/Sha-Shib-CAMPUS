<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The admin panel is for staff only; signed-in students are sent to their application.
 */
class RedirectStudentsToPortal
{
    public function handle(Request $request, Closure $next)
    {
        // A student who signed in on the admin login page: move the login to the portal's own guard.
        if (Auth::check() && Auth::user()->hasRole('student')) {
            $user = Auth::user();
            Auth::guard(UseStudentGuard::GUARD)->login($user);
            Auth::guard('web')->logout();

            return redirect()->route('portal.status');
        }

        return $next($request);
    }
}
