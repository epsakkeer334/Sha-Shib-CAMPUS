<?php

namespace App\Http\Livewire\Admin\Dashboard;

use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Role-based dashboard: Super Admin (system-wide), Institute Admin, Accounts, Training Manager,
 * and a staff dashboard for the other roles (BiC, EM, HoT, Faculty). Data: DashboardService.
 */
class Dashboard extends Component
{
    public function render(DashboardService $dashboards)
    {
        $user = Auth::user();
        $type = $dashboards->dashboardFor($user);

        $data = match ($type) {
            'super-admin' => $dashboards->superAdmin($user),
            'institute-admin' => $dashboards->instituteAdmin($user),
            'accounts' => $dashboards->accounts($user),
            'training-manager' => $dashboards->trainingManager($user),
            default => $dashboards->staff($user),
        };

        $data['pending'] = $dashboards->pendingActions($user); // highlighted on every dashboard

        return view('livewire.admin.dashboard.dashboard', ['type' => $type, 'd' => $data])
            ->layout('layouts.admin.master');
    }
}
