{{-- My notifications (in-app bell). Params: notifications = [unread, latest] --}}
<div class="db-panel">
    <div class="db-panel-head">
        <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-bell"></i></span><div><h5>My Notifications</h5><small>{{ $notifications['unread'] ? $notifications['unread'] . ' unread' : 'You are all caught up' }}</small></div></div>
        @if(\Illuminate\Support\Facades\Route::has('admin.my-notifications'))<a href="{{ route('admin.my-notifications') }}" class="db-link">View all <i class="ti ti-arrow-right"></i></a>@endif
    </div>
    <div class="db-panel-body pt-1 pb-1">
        @forelse($notifications['latest'] as $n)
            @php $tone = ['success' => 'green', 'warning' => 'amber', 'danger' => 'red', 'info' => 'sky', 'primary' => 'orange'][$n->data['tone'] ?? 'primary'] ?? 'orange'; @endphp
            <div class="db-row">
                <span class="db-av db-av-round tone-{{ $tone }}"><i class="{{ $n->data['icon'] ?? 'ti ti-bell' }}"></i></span>
                <div class="db-row-main">
                    <div class="db-row-title {{ $n->read_at ? 'fw-medium' : '' }}">{{ $n->data['title'] ?? 'Notification' }}</div>
                    <div class="db-row-sub">{{ \Illuminate\Support\Str::limit($n->data['message'] ?? '', 70) }}</div>
                </div>
                <span class="db-row-sub text-nowrap">{{ $n->created_at->diffForHumans(null, true) }}</span>
                @unless($n->read_at)<i class="ti ti-point-filled text-danger"></i>@endunless
            </div>
        @empty
            <div class="db-empty"><i class="ti ti-bell-off"></i>No notifications yet.</div>
        @endforelse
    </div>
</div>
