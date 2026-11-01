@php
    $tones = [
        'indigo' => ['#E6ECFF', '#2338A8'], 'amber' => ['#FFF2DB', '#8A4B00'], 'green' => ['#E3F6EC', '#0B6B43'],
        'red' => ['#FDE7E5', '#A01F14'], 'sky' => ['#E0F2FE', '#075985'],
    ];
@endphp
<main class="main">
    <header class="page-head rise">
        <span class="eyebrow">Notifications</span>
        <h1 class="title">Updates on your application</h1>
    </header>

    <section class="card flush rise" style="animation-delay: .08s;">
        <div class="pn-head">
            <div class="pn-filter" role="group" aria-label="Filter">
                <button type="button" class="pn-pill {{ !$unreadOnly ? 'is-active' : '' }}" wire:click="$set('unreadOnly', false)">All</button>
                <button type="button" class="pn-pill {{ $unreadOnly ? 'is-active' : '' }}" wire:click="$set('unreadOnly', true)">Unread @if($unread)<span class="pn-count">{{ $unread }}</span>@endif</button>
            </div>
            @if($unread)
                <button type="button" class="btn btn-secondary btn-sm" wire:click="markAllRead">Mark all as read</button>
            @endif
        </div>

        @forelse($notifications as $n)
            @php
                $d = $n->data;
                [$bg, $fg] = $tones[$d['tone'] ?? 'indigo'] ?? $tones['indigo'];
                $isUnread = is_null($n->read_at);
            @endphp
            <a href="#" wire:click.prevent="open('{{ $n->id }}')" wire:key="pn-{{ $n->id }}" class="pn-item {{ $isUnread ? 'is-unread' : '' }}">
                <span class="pn-mark" style="background: {{ $bg }}; color: {{ $fg }};" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6"/><path d="M9 17v1a3 3 0 0 0 6 0v-1"/></svg>
                </span>
                <span class="pn-body">
                    <span class="pn-title">{{ $d['title'] ?? 'Update' }} @if($isUnread)<span class="pn-dot" title="New"></span>@endif</span>
                    <span class="pn-msg">{{ $d['message'] ?? '' }}</span>
                    <span class="pn-meta">
                        @if(!empty($d['stage_label']))<span class="pn-stage" style="background: {{ $bg }}; color: {{ $fg }};">{{ $d['stage_label'] }}</span>@endif
                        <span title="{{ $n->created_at->format('d M Y, h:i A') }}">{{ $n->created_at->diffForHumans() }}</span>
                    </span>
                </span>
            </a>
        @empty
            <div class="pn-empty">
                <strong>{{ $unreadOnly ? 'No unread updates' : 'No updates yet' }}</strong>
                <span class="muted">You will be notified here when your application moves to the next step.</span>
            </div>
        @endforelse

        @if($notifications->hasPages())
            <div style="padding: 16px 24px; border-top: 1px solid var(--line-2);">{{ $notifications->links() }}</div>
        @endif
    </section>

    <div class="on-dark"><a href="{{ route('portal.status') }}">← Back to my application</a></div>

    <style>
        .pn-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 20px; border-bottom: 1px solid var(--line-2); }
        .pn-filter { display: inline-flex; gap: 4px; padding: 4px; background: rgba(232, 236, 245, .8); border-radius: 12px; }
        .pn-pill { border: 0; background: transparent; padding: 7px 14px; border-radius: 9px; font-weight: 600; font-size: 14px; color: var(--ink-2); cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background-color .15s ease; }
        .pn-pill.is-active { background: #fff; color: var(--ink); font-weight: 800; box-shadow: 0 2px 6px rgba(11, 23, 51, .1); }
        .pn-count { min-width: 20px; padding: 0 6px; border-radius: 999px; background: var(--grad); color: #fff; font-size: 12px; font-weight: 700; text-align: center; }
        .pn-item { display: flex; gap: 14px; padding: 16px 20px; border-bottom: 1px solid var(--line-2); text-decoration: none; color: var(--ink); transition: background-color .15s ease; }
        .pn-item:hover { background: rgba(238, 241, 255, .8); color: var(--ink); }
        .pn-item.is-unread { background: rgba(255, 248, 230, .75); }
        .pn-mark { width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .pn-body { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
        .pn-title { font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .pn-item:not(.is-unread) .pn-title { font-weight: 500; }
        .pn-msg { color: var(--ink-2); font-size: 14px; line-height: 20px; }
        .pn-meta { display: flex; align-items: center; gap: 10px; font-size: 12.5px; color: var(--muted); margin-top: 4px; }
        .pn-stage { padding: 1px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; }
        .pn-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); display: inline-block; box-shadow: 0 0 0 3px rgba(242, 181, 68, .25); }
        .pn-empty { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 48px 24px; text-align: center; }
    </style>
</main>
