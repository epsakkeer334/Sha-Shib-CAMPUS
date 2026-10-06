<div class="content nt-page">
    {{-- Header --}}
    <div class="nt-hero mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Notifications</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item active">Notifications</li>
                </ol>
            </nav>
            <div class="text-muted small">Workflow updates about students in your institute — registrations, documents, payments, gates, ER numbers and ID cards.</div>
        </div>
        @if($counts['unread'])
            <button type="button" class="nt-hero-cta" wire:click="markAllRead"><i class="ti ti-checks"></i> Mark all as read</button>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="nt-tabs mb-3">
        @foreach(['all' => ['All', 'ti ti-inbox'], 'unread' => ['Unread', 'ti ti-mail'], 'read' => ['Read', 'ti ti-mail-opened']] as $key => [$label, $icon])
            <button type="button" class="nt-tab {{ $tab === $key ? 'is-active' : '' }}" wire:click="$set('tab', '{{ $key }}')" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}">
                <i class="{{ $icon }}"></i> {{ $label }} <span class="nt-tab-count {{ $key === 'unread' && $counts['unread'] ? 'is-hot' : '' }}">{{ number_format($counts[$key]) }}</span>
            </button>
        @endforeach
    </div>

    <div class="nt-panel">
        {{-- Filters --}}
        <div class="nt-filters">
            <div class="nt-search">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control form-control-sm" placeholder="Student, document, ER number…" wire:model.debounce.400ms="search" aria-label="Search notifications">
            </div>
            <select class="form-select form-select-sm" wire:model="stage" aria-label="Workflow stage">
                <option value="">All stages</option>
                @foreach($stages as $key => [$label])<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
            @if($stage || $search)
                <button type="button" class="btn btn-sm btn-light" wire:click="clearFilters"><i class="ti ti-x me-1"></i> Clear</button>
            @endif
            <span class="ms-auto small text-muted">{{ number_format($notifications->total()) }} {{ \Illuminate\Support\Str::plural('notification', $notifications->total()) }}</span>
        </div>

        <div class="position-relative">
            <div class="nt-loading" wire:loading.delay.flex wire:target="tab, stage, search, clearFilters, gotoPage, nextPage, previousPage, markAllRead">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
            @forelse($groups as $day => $items)
                <div class="nt-day">{{ $day }}</div>
                @foreach($items as $n)
                    <div class="nt-row" wire:key="nt-{{ $n->id }}">
                        <a href="#" class="flex-grow-1 min-w-0 text-decoration-none" wire:click.prevent="open('{{ $n->id }}')">
                            @include('livewire.admin.notifications.partials.item', ['n' => $n, 'compact' => false])
                        </a>
                        <div class="nt-actions">
                            <span class="nt-time">{{ $n->created_at->format('h:i A') }}</span>
                            <button type="button" class="btn btn-sm btn-light" wire:click="toggleRead('{{ $n->id }}')"
                                    title="{{ $n->read_at ? 'Mark as unread' : 'Mark as read' }}" aria-label="{{ $n->read_at ? 'Mark as unread' : 'Mark as read' }}">
                                <i class="ti ti-{{ $n->read_at ? 'mail' : 'mail-opened' }}"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            @empty
                <div class="nt-empty-page">
                    <span class="nt-empty-icon"><i class="ti ti-bell-off"></i></span>
                    <div class="fw-semibold">{{ $stage || $search ? 'No notifications match these filters' : ($tab === 'unread' ? 'No unread notifications' : 'No notifications yet') }}</div>
                    <div class="small text-muted">Workflow updates will appear here as students move through admission.</div>
                </div>
            @endforelse
        </div>
        @if($notifications->hasPages())<div class="nt-panel-foot">{{ $notifications->links() }}</div>@endif
    </div>

    @include('livewire.admin.notifications.partials.styles')
    <style>
        .nt-page .min-w-0 { min-width: 0; }
        .nt-page .nt-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid #E5E7EB;
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .nt-page .nt-hero h2 { font-size: 22px; color: #111827; }
        .nt-page .nt-hero-cta { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border-radius: 999px; border: 0; background: #F26522; color: #fff; font-size: 14px; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); }
        .nt-page .nt-tabs { display: inline-flex; gap: 4px; padding: 4px; background: #F3F4F6; border-radius: 12px; }
        .nt-page .nt-tab { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border: 0; border-radius: 9px; background: transparent; color: #4B5563; font-size: 13.5px; font-weight: 500; }
        .nt-page .nt-tab.is-active { background: #fff; color: #111827; box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
        .nt-page .nt-tab-count { min-width: 22px; padding: 0 7px; border-radius: 999px; background: #E5E7EB; font-size: 11.5px; font-weight: 600; }
        .nt-page .nt-tab-count.is-hot { background: #F26522; color: #fff; }
        .nt-page .nt-panel { background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .nt-page .nt-panel-foot { padding: 12px 16px; border-top: 1px solid #F1F2F4; }
        .nt-page .nt-panel-foot .pagination { margin: 0; }
        .nt-page .nt-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid #F1F2F4; background: #FCFCFD; }
        .nt-page .nt-filters .form-select { width: auto; min-width: 160px; border-radius: 8px; }
        .nt-page .nt-search { position: relative; width: 280px; max-width: 100%; }
        .nt-page .nt-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .nt-page .nt-search input { padding-left: 32px; border-radius: 8px; }
        .nt-page .nt-day { padding: 8px 16px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #6B7280; background: #F9FAFB; border-bottom: 1px solid #F1F2F4; }
        .nt-page .nt-row { display: flex; align-items: stretch; border-bottom: 1px solid #F1F2F4; }
        .nt-page .nt-row .nt-item { border-bottom: 0; padding: 14px 16px; }
        .nt-page .nt-actions { display: flex; flex-direction: column; align-items: flex-end; justify-content: center; gap: 6px; padding: 10px 16px 10px 4px; flex-shrink: 0; }
        .nt-page .nt-row:has(.is-unread) .nt-actions { background: #FFF8F3; }
        .nt-page .nt-time { font-size: 11.5px; color: #9CA3AF; white-space: nowrap; }
        .nt-page .nt-loading { display: none; position: absolute; inset: 0; z-index: 2; background: rgba(255, 255, 255, .6); align-items: center; justify-content: center; }
        .nt-page .nt-empty-page { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 56px 16px; text-align: center; }
        .nt-page .nt-empty-icon { width: 64px; height: 64px; border-radius: 50%; background: #FFF4EC; color: #F26522; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 8px; }
        @media (max-width: 575.98px) { .nt-page .nt-search, .nt-page .nt-filters .form-select { width: 100%; } .nt-page .nt-time { display: none; } }
    </style>
</div>
