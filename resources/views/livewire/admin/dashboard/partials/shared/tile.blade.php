{{-- Stat tile (template "Widget Info" card). Params: icon, tone, label, value, extra (HTML, optional), link, route (optional), col --}}
<div class="{{ $col ?? 'col-md-3' }} d-flex">
    <div class="card flex-fill">
        <div class="card-body">
            <span class="avatar rounded-circle bg-{{ $tone }} mb-2"><i class="{{ $icon }} fs-16"></i></span>
            <h6 class="fs-13 fw-medium text-default mb-1">{{ $label }}</h6>
            <h3 class="mb-3">{{ $value }} @if(!empty($extra))<span class="fs-12 fw-medium">{!! $extra !!}</span>@endif</h3>
            @if(!empty($route) && \Illuminate\Support\Facades\Route::has($route))
                <a href="{{ route($route) }}" class="link-default">{{ $link ?? 'View All' }}</a>
            @elseif(!empty($link))
                <span class="text-muted fs-13">{{ $link }}</span>
            @endif
        </div>
    </div>
</div>
