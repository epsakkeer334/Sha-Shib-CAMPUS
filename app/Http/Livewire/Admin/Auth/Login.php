<?php

namespace App\Http\Livewire\Admin\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Jenssegers\Agent\Agent;
use App\Http\Middleware\EnsureUserIsActive;

class Login extends Component
{
    public $email = '';
    public $password = '';
    public $remember = false;
    public $errorMessage = '';

    public function mount()
    {
        // Set by EnsureUserIsActive when a signed-in user is logged out.
        $this->errorMessage = session('error', '');
    }

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

            if ($reason = EnsureUserIsActive::blockReason($user)) {
                Auth::logout();
                $this->errorMessage = $reason;
                return;
            }

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

            // Every role uses the same dashboard; its content and the side menu depend on the role.
            return redirect()->intended(route('admin.dashboard'));
        }

        $this->errorMessage = 'Invalid credentials. Please try again.';
    }

    public function render()
    {
        return view('livewire.admin.auth.login')
            ->layout('layouts.admin.auth.auth');
    }
}
