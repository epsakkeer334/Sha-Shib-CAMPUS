{{-- Recent Activities (audit trail). Params: entries, col --}}
@php $label = fn ($v) => \App\Support\Readable::label((string) $v); @endphp
<div class="{{ $col ?? 'col-xxl-4 col-xl-6' }} d-flex">
    <div class="card flex-fill">
        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
            <h5 class="mb-2">Recent Activities</h5>
            @if(auth()->user()->can('audit.view'))<a href="{{ route('admin.audit-trail') }}" class="btn btn-light btn-md mb-2">View All</a>@endif
        </div>
        <div class="card-body">
            @forelse($entries as $e)
                @php $initials = $e->user ? collect(preg_split('/\s+/', trim($e->user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') : null; @endphp
                <div class="recent-item">
                    <div class="d-flex align-items-center w-100">
                        <span class="avatar flex-shrink-0 rounded-circle {{ $e->user ? 'bg-primary-transparent text-primary' : 'bg-light text-muted' }} fw-semibold">{!! $initials ? e($initials) : '<i class="ti ti-robot"></i>' !!}</span>
                        <div class="ms-2 flex-fill overflow-hidden">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6 class="fs-medium text-truncate">{{ optional($e->user)->name ?? 'System' }}</h6>
                                <p class="fs-13 text-nowrap ms-2 mb-0">{{ $e->created_at->isToday() ? $e->created_at->format('h:i A') : $e->created_at->format('d M') }}</p>
                            </div>
                            <p class="fs-13 text-truncate mb-0" title="{{ $e->meta['description'] ?? '' }}">{{ $e->meta['description'] ?? ($label($e->action) . ' ' . $label($e->module)) }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-muted text-center py-4 mb-0">No activity yet.</p>
            @endforelse
        </div>
    </div>
</div>
