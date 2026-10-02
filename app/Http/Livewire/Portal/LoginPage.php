<?php

namespace App\Http\Livewire\Portal;

use App\Http\Middleware\EnsureUserIsActive;
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
        if (Auth::check()) {
            return redirect()->route(Auth::user()->hasRole('student') ? 'portal.status' : 'admin.dashboard');
        }

        $this->errorMessage = session('error', '');
    }

    public function login()
    {
        $this->validate(['email' => 'required|email', 'password' => 'required']);

        $key = 'portal-login:' . Str::lower($this->email) . '|' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->errorMessage = 'Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.';

            return null;
        }

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            $this->errorMessage = 'The email or password is not correct.';

            return null;
        }

        RateLimiter::clear($key);
        $user = Auth::user();

        if (!$user->hasRole('student')) {
            Auth::logout();
            $this->errorMessage = 'This is the student admissions portal. Staff sign in at the admin panel.';

            return null;
        }

        if (($reason = EnsureUserIsActive::blockReason($user)) || !$user->student) {
            Auth::logout();
            $this->errorMessage = $reason ?? 'No application is linked to this account.';

            return null;
        }

        session()->regenerate();
        $user->update(['last_login_at' => now(), 'last_login_ip' => request()->ip()]);

        return redirect()->intended(route('portal.status'));
    }

    public function render()
    {
        return view('portal.login')->layout('layouts.portal', ['title' => 'Sign in']);
    }
}
