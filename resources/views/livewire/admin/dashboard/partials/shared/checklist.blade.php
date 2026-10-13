{{-- Set-up checklist (template "Todo" card). Params: title, items = [[done, text, hint, route]], col --}}
@php $done = collect($items)->filter(fn ($i) => $i[0])->count(); @endphp
<div class="{{ $col ?? 'col-xxl-4 col-xl-6' }} d-flex">
    <div class="card flex-fill">
        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
            <h5 class="mb-2">{{ $title }}</h5>
            <span class="badge {{ $done === count($items) ? 'badge-success' : 'badge-warning' }} mb-2">{{ $done }}/{{ count($items) }} done</span>
        </div>
        <div class="card-body">
            @foreach($items as [$ok, $text, $hint, $route])
                <div class="d-flex align-items-center justify-content-between todo-item border p-2 br-5 mb-2 {{ $ok ? '' : 'border-warning' }}">
                    <div class="d-flex align-items-center overflow-hidden">
                        <i class="ti {{ $ok ? 'ti-circle-check-filled text-success' : 'ti-alert-circle-filled text-warning' }} fs-18 me-2"></i>
                        <div class="overflow-hidden">
                            <p class="fw-medium mb-0 text-truncate {{ $ok ? 'text-muted' : 'text-dark' }}">{{ $text }}</p>
                            @if(!$ok && $hint)<span class="fs-12 text-muted d-block text-truncate">{{ $hint }}</span>@endif
                        </div>
                    </div>
                    @if(!$ok && $route && \Illuminate\Support\Facades\Route::has($route))
                        <a href="{{ route($route) }}" class="btn btn-light btn-sm p-0 btn-icon d-flex align-items-center justify-content-center flex-shrink-0 ms-2" title="Fix"><i class="ti ti-arrow-right"></i></a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
