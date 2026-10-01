<?php

namespace App\Http\Livewire\Admin\Notifications;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Read-only list of SMS/Email notifications sent through NotificationService.
 */
class NotificationLogComponent extends Component
{
    public function mount()
    {
        abort_unless(Auth::user()->can('notifications.view'), 403);
    }

    public function render()
    {
        return view('livewire.admin.notifications.notification-log-component')->layout('layouts.admin.master');
    }
}
