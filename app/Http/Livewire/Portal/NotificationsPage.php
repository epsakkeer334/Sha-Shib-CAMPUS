<?php

namespace App\Http\Livewire\Portal;

use App\Http\Livewire\Portal\Concerns\StudentPortalPage;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Student portal: updates about the student's own application (documents, payments, gates,
 * ER number, ID card). Header bell links here with the unread count.
 */
class NotificationsPage extends Component
{
    use StudentPortalPage, WithPagination;

    public $unreadOnly = false;

    public function updatedUnreadOnly()
    {
        $this->resetPage();
    }

    public function open($id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('portal.status'));
    }

    public function markAllRead()
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
        $this->dispatchBrowserEvent('portal-unread', ['count' => 0]); // header bell badge
    }

    public function paginationView()
    {
        return 'livewire::simple-tailwind';
    }

    public function render()
    {
        $user = Auth::user();

        return view('portal.notifications', [
            'student' => $this->student(),
            'notifications' => $user->notifications()->when($this->unreadOnly, fn ($q) => $q->whereNull('read_at'))->latest()->orderByDesc('id')->paginate(15),
            'unread' => $user->unreadNotifications()->count(),
        ])->layout('layouts.portal', ['title' => 'Notifications']);
    }
}
