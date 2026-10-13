{{-- My notifications (in-app bell). Params: notifications = [unread, latest], col --}}
<div class="{{ $col ?? 'col-xxl-4 col-xl-6' }} d-flex">
    <div class="card flex-fill">
        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
            <h5 class="mb-2">My Notifications @if($notifications['unread'])<span class="badge badge-danger ms-1">{{ $notifications['unread'] }} new</span>@endif</h5>
            @if(\Illuminate\Support\Facades\Route::has('admin.my-notifications'))<a href="{{ route('admin.my-notifications') }}" class="btn btn-light btn-md mb-2">View All</a>@endif
        </div>
        <div class="card-body">
            @forelse($notifications['latest'] as $n)
                <div class="d-flex align-items-start mb-3 {{ $n->read_at ? '' : 'fw-medium' }}">
                    <span class="avatar avatar-sm rounded-circle bg-{{ $n->data['tone'] ?? 'primary' }}-transparent text-{{ $n->data['tone'] ?? 'primary' }} flex-shrink-0"><i class="{{ $n->data['icon'] ?? 'ti ti-bell' }}"></i></span>
                    <div class="ms-2 overflow-hidden">
                        <p class="mb-0 text-dark text-truncate">{{ $n->data['title'] ?? 'Notification' }}</p>
                        <span class="fs-12 text-muted">{{ \Illuminate\Support\Str::limit($n->data['message'] ?? '', 70) }} · {{ $n->created_at->diffForHumans() }}</span>
                    </div>
                    @unless($n->read_at)<i class="ti ti-point-filled text-danger ms-auto"></i>@endunless
                </div>
            @empty
                <p class="text-muted text-center py-4 mb-0"><i class="ti ti-bell-off d-block fs-24 mb-1"></i>No notifications yet.</p>
            @endforelse
        </div>
    </div>
</div>
