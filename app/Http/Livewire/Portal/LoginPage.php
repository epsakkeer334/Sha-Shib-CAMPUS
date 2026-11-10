<?php

namespace App\Http\Livewire\Portal;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use App\Services\LoginLogger;
use App\Support\PortalProgress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Student sign-in for the admissions portal. Staff accounts are pointed to the admin login.
 */
class LoginPage extends Component
{
    public $email = '';
    public $password = '';
    public $remember = false;
    public $errorMessage = '';

    public function mount()
    {
        // Portal (student) login only — a staff member signed in to the admin panel just sees the sign-in form.
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->hasRole('student') && $user->student) {
                return redirect()->route(PortalProgress::resumeRoute($user->student));
            }
            Auth::logout();
        }

        $this->errorMessage = session('error', '');
    }

    public function login()
    {
        $this->validate(['email' => 'required|email', 'password' => 'required']);

        $key = 'portal-login:' . Str::lower($this->email) . '|' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            LoginLogger::blocked(User::where('email', $this->email)->first(), 'portal', 'Too many attempts — sign-in paused for ' . RateLimiter::availableIn($key) . ' s', $this->email);
            $this->errorMessage = 'Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.';

            return null;
        }

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            LoginLogger::failed($this->email, 'portal');
            $this->errorMessage = 'The email or password is not correct.';

            return null;
        }

        RateLimiter::clear($key);
        $user = Auth::user();

        if (!$user->hasRole('student')) {
            LoginLogger::blocked($user, 'portal', 'Staff account used the student portal');
            Auth::logout();
            $this->errorMessage = 'This is the student admissions portal. Staff sign in at the admin panel.';

            return null;
        }

        if (($reason = EnsureUserIsActive::blockReason($user)) || !$user->student) {
            LoginLogger::blocked($user, 'portal', $reason ?? 'No application is linked to this account');
            Auth::logout();
            $this->errorMessage = $reason ?? 'No application is linked to this account.';

            return null;
        }

        session()->regenerate();
        $user->update(['last_login_at' => now(), 'last_login_ip' => request()->ip()]);
        LoginLogger::login($user, 'portal');

        // Continue where the student left off (or the page they were trying to open).
        return redirect()->intended(route(PortalProgress::resumeRoute($user->student)));
    }

    public function render()
    {
        return view('portal.login')->layout('layouts.portal', ['title' => 'Sign in']);
    }
}
