{{-- Set-up checklist. Params: title, items = [[done, text, hint, route]] --}}
@php $done = collect($items)->filter(fn ($i) => $i[0])->count(); $total = count($items); @endphp
<div class="db-panel">
    <div class="db-panel-head">
        <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-list-check"></i></span><div><h5>{{ $title }}</h5><small>{{ $done }} of {{ $total }} done</small></div></div>
        <span class="db-chip {{ $done === $total ? 'tone-green' : 'tone-amber' }}">{{ $total ? round($done / $total * 100) : 100 }}%</span>
    </div>
    <div class="db-panel-body">
        <div class="db-bar mb-3 tone-green"><span style="width: {{ $total ? round($done / $total * 100) : 100 }}%"></span></div>
        @foreach($items as [$ok, $text, $hint, $route])
            <div class="db-row">
                <i class="ti {{ $ok ? 'ti-circle-check-filled text-success' : 'ti-alert-circle-filled text-warning' }} fs-18"></i>
                <div class="db-row-main">
                    <div class="db-row-title {{ $ok ? 'text-muted fw-medium' : '' }}">{{ $text }}</div>
                    @if(!$ok && $hint)<div class="db-row-sub">{{ $hint }}</div>@endif
                </div>
                @if(!$ok && $route && \Illuminate\Support\Facades\Route::has($route))
                    <a href="{{ route($route) }}" class="db-link">Fix <i class="ti ti-arrow-right"></i></a>
                @endif
            </div>
        @endforeach
    </div>
</div>
