<div class="dropdown me-1 nt-bell" wire:poll.30s wire:ignore.self>
    <a href="#" class="btn btn-menubar position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
       aria-label="Notifications{{ $unread ? " ({$unread} unread)" : '' }}" title="Notifications">
        <i class="ti ti-bell {{ $unread ? 'nt-ring' : '' }}"></i>
        @if($unread)
            <span class="nt-badge">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </a>
    <div class="dropdown-menu dropdown-menu-end p-0 nt-dropdown" wire:ignore.self>
        <div class="nt-dropdown-head">
            <div>
                <div class="fw-semibold">Notifications</div>
                <div class="small text-muted">{{ $unread ? "{$unread} unread" : 'You are all caught up' }}</div>
            </div>
            @if($unread)
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" wire:click="markAllRead"><i class="ti ti-checks"></i> Mark all read</button>
            @endif
        </div>
        <div class="nt-dropdown-body">
            @forelse($latest as $n)
                <a href="#" class="d-block text-decoration-none" wire:click.prevent="open('{{ $n->id }}')" wire:key="bell-{{ $n->id }}">
                    @include('livewire.admin.notifications.partials.item', ['n' => $n, 'compact' => true])
                </a>
            @empty
                <div class="nt-empty"><i class="ti ti-bell-off"></i><div>No notifications yet</div></div>
            @endforelse
        </div>
        <a href="{{ route('admin.my-notifications') }}" class="nt-dropdown-foot">View all notifications <i class="ti ti-arrow-right"></i></a>
    </div>

    @include('livewire.admin.notifications.partials.styles')
    <style>
        .nt-bell .btn-menubar i { font-size: 18px; }
        .nt-badge { position: absolute; top: -3px; right: -4px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: #EF4444; color: #fff; font-size: 10.5px; font-weight: 700; line-height: 18px; text-align: center; border: 2px solid #fff; }
        .nt-ring { display: inline-block; animation: ntRing 2.4s ease-in-out infinite; transform-origin: 50% 0; }
        @keyframes ntRing { 0%, 80%, 100% { transform: rotate(0); } 84% { transform: rotate(14deg); } 88% { transform: rotate(-12deg); } 92% { transform: rotate(8deg); } 96% { transform: rotate(-4deg); } }
        .nt-dropdown { width: 380px; max-width: calc(100vw - 24px); border: 1px solid #E5E7EB; border-radius: 14px; box-shadow: 0 16px 40px rgba(16, 24, 40, .16); overflow: hidden; }
        .nt-dropdown-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 14px; border-bottom: 1px solid #F1F2F4; }
        .nt-dropdown-body { max-height: 420px; overflow-y: auto; }
        .nt-dropdown-body .nt-item:last-child { border-bottom: 0; }
        .nt-dropdown-foot { display: block; padding: 10px; text-align: center; font-weight: 600; font-size: 13px; color: #F26522; border-top: 1px solid #F1F2F4; background: #FCFCFD; text-decoration: none; }
        .nt-dropdown-foot:hover { background: #FFF7F2; }
        .nt-empty { text-align: center; color: #9CA3AF; padding: 36px 12px; font-size: 13px; }
        .nt-empty i { font-size: 32px; display: block; margin-bottom: 6px; color: #D1D5DB; }
    </style>
</div>
