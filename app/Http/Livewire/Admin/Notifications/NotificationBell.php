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

    public function open($id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('admin.my-notifications'));
    }

    public function markAllRead()
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
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
