<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            // Signed in on the admissions portal only (a student): back to their application, not the staff login.
            if (\Illuminate\Support\Facades\Auth::guard(UseStudentGuard::GUARD)->check()) {
                return route('portal.status');
            }

            return route('admin.login');
        }
    }
}
