<?php

namespace App\Http\Livewire\Admin\Notifications;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Header bell: unread badge + dropdown with the 5 latest notifications (polled every 30 s).
 */
class NotificationBell extends Component
{
    const LATEST = 5;

    // Read / unread changed elsewhere (e.g. the "View all" page) → refresh the badge immediately
    protected $listeners = ['notificationsUpdated' => '$refresh'];

    public function open($id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('admin.my-notifications'));
    }

    public function markAllRead()
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
        $this->emit('notificationsUpdated'); // e.g. the notifications page, if open
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.admin.notifications.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->latest()->orderByDesc('id')->take(self::LATEST)->get(),
        ]);
    }
}
