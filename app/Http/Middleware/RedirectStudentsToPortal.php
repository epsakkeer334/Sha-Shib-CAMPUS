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
        if (Auth::check() && Auth::user()->hasRole('student')) {
            return redirect()->route('portal.status');
        }

        return $next($request);
    }
}
