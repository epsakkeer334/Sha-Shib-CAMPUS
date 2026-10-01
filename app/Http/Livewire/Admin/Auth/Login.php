<?php

namespace App\Http\Livewire\Admin\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Jenssegers\Agent\Agent;

class Login extends Component
{
    public $email = '';
    public $password = '';
    public $remember = false;
    public $errorMessage = '';

    protected function getDeviceInfo()
    {
        $agent = new Agent();

        return [
            'device' => $agent->device(),
            'platform' => $agent->platform(),
            'browser' => $agent->browser(),
            'is_desktop' => $agent->isDesktop(),
            'is_mobile' => $agent->isMobile(),
            'is_tablet' => $agent->isTablet(),
        ];
    }

    public function login()
    {
        $credentials = $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:4',
        ]);

        if (Auth::attempt($credentials, $this->remember)) {
            session()->regenerate();

            $user = Auth::user();

            // Update last login information
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ]);

            // Optional: Store login session info
            Session::put('login_info', [
                'time' => now()->toDateTimeString(),
                'ip' => request()->ip(),
                'device_info' => $this->getDeviceInfo(),
            ]);

            // ✅ Redirect based on role
            if ($user->hasRole('super-admin')) {
                return redirect()->intended(route('admin.dashboard'));
            } elseif ($user->hasRole('vendor')) {
                return redirect()->intended(route('vendor.dashboard'));
            } elseif ($user->hasRole('employee')) {
                return redirect()->intended(route('employee.dashboard'));
            } elseif ($user->hasRole('institute-admin')) {
                return redirect()->intended(route('institute.dashboard'));
            } elseif ($user->hasRole('accounts')) {
                return redirect()->intended(route('accounts.dashboard'));
            } elseif ($user->hasRole('training-manager')) {
                return redirect()->intended(route('tm.dashboard'));
            } elseif ($user->hasRole('hot')) {
                return redirect()->intended(route('hot.dashboard'));
            } elseif ($user->hasRole('bic')) {
                return redirect()->intended(route('bic.dashboard'));
            } elseif ($user->hasRole('faculty')) {
                return redirect()->intended(route('faculty.dashboard'));
            } elseif ($user->hasRole('student')) {
                return redirect()->intended(route('student.dashboard'));
            } else {
                // fallback in case of no role
                Auth::logout();
                $this->errorMessage = 'Unauthorized access — please contact admin.';
                return;
            }
        }

        $this->errorMessage = 'Invalid credentials. Please try again.';
    }

    public function render()
    {
        return view('livewire.admin.auth.login')
            ->layout('layouts.admin.auth.auth');
    }
}
