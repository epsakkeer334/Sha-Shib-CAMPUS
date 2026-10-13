{{-- "Waiting for you" (compact): pending approvals the user can act on (DashboardService::pendingActions). Params: pending --}}
@php
    $items = collect($pending);
    $waiting = $items->sum('count');
    $open = $items->filter(fn ($i) => $i['count'] > 0);
    $clear = $items->filter(fn ($i) => $i['count'] === 0);
@endphp
@if($items->isNotEmpty())
    <div class="pa-bar {{ $waiting ? 'pa-has-work' : 'pa-clear' }} mb-3">
        <div class="pa-head">
            <span class="pa-bell"><i class="ti {{ $waiting ? 'ti-bell-ringing' : 'ti-circle-check' }}"></i></span>
            <span class="pa-title">Waiting for you</span>
            <span class="pa-badge">{{ $waiting ? $waiting . ' pending' : 'All clear' }}</span>
        </div>

        <div class="pa-chips">
            @forelse($open as $it)
                <a href="{{ $it['url'] }}" class="pa-chip pa-tone-{{ $it['tone'] }}" data-pending="{{ $it['key'] }}" title="{{ $it['group'] }} — {{ $it['hint'] }}">
                    <i class="{{ $it['icon'] }}"></i>
                    <span class="pa-chip-label">{{ $it['label'] }}</span>
                    <span class="pa-chip-count">{{ $it['count'] }}</span>
                    <span class="pa-dot"></span>
                </a>
            @empty
                <span class="pa-none">No approvals or tasks waiting for you.</span>
            @endforelse
        </div>

        @if($clear->isNotEmpty())
            <button type="button" class="pa-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#paClear" aria-expanded="false" aria-controls="paClear">
                <span class="pa-show">Show all</span><span class="pa-hide">Hide</span> <i class="ti ti-chevron-down"></i>
            </button>
        @endif
    </div>
    @if($clear->isNotEmpty())
        <div class="collapse" id="paClear">
            <div class="pa-clear-list mb-3">
                @foreach($clear as $it)
                    <a href="{{ $it['url'] }}" class="pa-chip is-clear" data-pending="{{ $it['key'] }}" title="{{ $it['group'] }}">
                        <i class="{{ $it['icon'] }}"></i><span class="pa-chip-label">{{ $it['label'] }}</span><span class="pa-chip-count">0</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <style>
        .pa-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 14px; background: #fff; border: 1px solid #E5E7EB; border-left: 4px solid #03C95A; border-radius: 12px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        .pa-bar.pa-has-work { border-left-color: #E70D0D; }
        .pa-head { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .pa-bell { width: 32px; height: 32px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 17px; background: #DCFCE7; color: #15803D; }
        .pa-has-work .pa-bell { background: #FEE2E2; color: #DC2626; animation: paRing 2.4s ease-in-out infinite; }
        @keyframes paRing { 0%, 80%, 100% { transform: rotate(0); } 84% { transform: rotate(14deg); } 88% { transform: rotate(-12deg); } 92% { transform: rotate(8deg); } 96% { transform: rotate(-4deg); } }
        .pa-title { font-size: 14px; font-weight: 700; color: #111827; white-space: nowrap; }
        .pa-badge { padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #DCFCE7; color: #15803D; white-space: nowrap; }
        .pa-has-work .pa-badge { background: #E70D0D; color: #fff; }
        .pa-chips { display: flex; flex-wrap: wrap; gap: 6px; flex: 1 1 auto; min-width: 0; }
        .pa-chip { --tone: #6B7280; --tone-soft: #F3F4F6; position: relative; display: inline-flex; align-items: center; gap: 6px; padding: 5px 6px 5px 10px; border-radius: 999px; background: var(--tone-soft); border: 1px solid var(--tone); color: var(--tone); font-size: 12.5px; font-weight: 600; text-decoration: none; transition: transform .12s, box-shadow .12s; white-space: nowrap; }
        .pa-chip:hover { color: var(--tone); transform: translateY(-1px); box-shadow: 0 4px 10px rgba(16, 24, 40, .08); }
        .pa-chip i { font-size: 15px; }
        .pa-chip-count { min-width: 22px; padding: 0 7px; border-radius: 999px; background: var(--tone); color: #fff; text-align: center; font-weight: 700; }
        .pa-dot { position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; border-radius: 50%; background: #E70D0D; animation: paPulse 1.8s infinite; }
        @keyframes paPulse { 0% { box-shadow: 0 0 0 0 rgba(231, 13, 13, .55); } 70% { box-shadow: 0 0 0 7px rgba(231, 13, 13, 0); } 100% { box-shadow: 0 0 0 0 rgba(231, 13, 13, 0); } }
        .pa-chip.is-clear { --tone: #9CA3AF; --tone-soft: #fff; border-color: #E5E7EB; color: #6B7280; font-weight: 500; }
        .pa-chip.is-clear .pa-chip-count { background: #F3F4F6; color: #9CA3AF; }
        .pa-none { font-size: 13px; color: #15803D; }
        .pa-toggle { margin-left: auto; border: 0; background: none; padding: 4px 6px; font-size: 12.5px; font-weight: 600; color: #6B7280; white-space: nowrap; }
        .pa-toggle:hover { color: #F26522; }
        .pa-toggle i { transition: transform .2s; display: inline-block; }
        .pa-toggle:not(.collapsed) i { transform: rotate(180deg); }
        .pa-toggle.collapsed .pa-hide, .pa-toggle:not(.collapsed) .pa-show { display: none; }
        .pa-clear-list { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 14px; background: #FAFAFA; border: 1px dashed #E5E7EB; border-radius: 12px; margin-top: -6px; }
        .pa-tone-warning { --tone: #B45309; --tone-soft: #FFFBEB; } .pa-tone-primary { --tone: #EA580C; --tone-soft: #FFF7ED; }
        .pa-tone-info { --tone: #1D4ED8; --tone-soft: #EFF6FF; } .pa-tone-danger { --tone: #B91C1C; --tone-soft: #FEF2F2; }
        .pa-tone-secondary { --tone: #334155; --tone-soft: #F1F5F9; }
    </style>
@endif
