<?php

namespace App\Http\Livewire\Admin\Notifications;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "View all" notifications of the logged-in user: read and unread, with tabs, a workflow-stage
 * filter, search, mark read / unread and mark all read. Grouped by day.
 */
class MyNotificationsComponent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    const PER_PAGE = 20;

    public $tab = 'all';      // all | unread | read
    public $stage = '';
    public $search = '';

    protected $queryString = ['tab' => ['except' => 'all'], 'stage' => ['except' => ''], 'search' => ['except' => '']];

    // The bell's "Mark all read" → refresh this list too
    protected $listeners = ['notificationsUpdated' => '$refresh'];

    public function updated($property)
    {
        if (in_array($property, ['tab', 'stage', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function open($id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('admin.my-notifications'));
    }

    public function toggleRead($id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->update(['read_at' => $notification->read_at ? null : now()]);
        $this->emitTo('admin.notifications.notification-bell', 'notificationsUpdated'); // bell badge updates at once
    }

    public function markAllRead()
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
        $this->emitTo('admin.notifications.notification-bell', 'notificationsUpdated');
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => 'All notifications marked as read.']);
    }

    public function clearFilters()
    {
        $this->reset(['stage', 'search']);
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();

        $query = $user->notifications()
            ->when($this->tab === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($this->tab === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->when($this->stage, fn ($q) => $q->where('data', 'like', '%"stage":"' . addcslashes($this->stage, '%_"\\') . '"%'));

        if ($term = trim($this->search)) {
            $query->where('data', 'like', '%' . addcslashes($term, '%_\\') . '%');
        }

        $notifications = $query->latest()->orderByDesc('id')->paginate(self::PER_PAGE);

        // Group the page by day: Today / Yesterday / date
        $groups = $notifications->getCollection()->groupBy(function ($n) {
            return $n->created_at->isToday() ? 'Today' : ($n->created_at->isYesterday() ? 'Yesterday' : $n->created_at->format('l, d M Y'));
        });

        return view('livewire.admin.notifications.my-notifications-component', [
            'notifications' => $notifications,
            'groups' => $groups,
            'counts' => [
                'all' => $user->notifications()->count(),
                'unread' => $user->unreadNotifications()->count(),
                'read' => $user->readNotifications()->count(),
            ],
            'stages' => config('workflow_notifications.stages'),
        ])->layout('layouts.admin.master');
    }
}
