{{-- Recent activity (audit trail). Params: entries, title (optional) --}}
@php $label = fn ($v) => \App\Support\Readable::label((string) $v); @endphp
<div class="db-panel">
    <div class="db-panel-head">
        <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-history"></i></span><div><h5>{{ $title ?? 'Recent Activities' }}</h5><small>Latest recorded actions</small></div></div>
        @if(auth()->user()->can('audit.view'))<a href="{{ route('admin.audit-trail') }}" class="db-link">View all <i class="ti ti-arrow-right"></i></a>@endif
    </div>
    <div class="db-panel-body pt-1 pb-1">
        @forelse($entries as $e)
            @php $initials = $e->user ? collect(preg_split('/\s+/', trim($e->user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') : null; @endphp
            <div class="db-row">
                <span class="db-av db-av-round {{ $e->user ? 'tone-indigo' : 'tone-slate' }}">{!! $initials ? e($initials) : '<i class="ti ti-robot"></i>' !!}</span>
                <div class="db-row-main">
                    <div class="db-row-title" title="{{ $e->meta['description'] ?? '' }}">{{ $e->meta['description'] ?? ($label($e->action) . ' ' . $label($e->module)) }}</div>
                    <div class="db-row-sub">{{ optional($e->user)->name ?? 'System' }} · {{ $label($e->module) }}</div>
                </div>
                <span class="db-row-sub text-nowrap">{{ $e->created_at->isToday() ? $e->created_at->format('h:i A') : $e->created_at->format('d M') }}</span>
            </div>
        @empty
            <div class="db-empty"><i class="ti ti-history-off"></i>No activity yet.</div>
        @endforelse
    </div>
</div>
