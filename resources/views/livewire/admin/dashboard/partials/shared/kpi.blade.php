{{-- KPI card. Params: label, value (HTML), hint (HTML, escaped by caller), icon, tone, route (optional), params (optional) --}}
@php $href = !empty($route) && \Illuminate\Support\Facades\Route::has($route) ? route($route, $params ?? []) : null; @endphp
@if($href)
    <a href="{{ $href }}" class="db-kpi tone-{{ $tone }}">
@else
    <div class="db-kpi tone-{{ $tone }}">
@endif
    <div class="db-kpi-top">
        <span class="db-kpi-label">{{ $label }}</span>
        <span class="db-kpi-icon"><i class="{{ $icon }}"></i></span>
    </div>
    <div class="db-kpi-value">{!! $value !!}</div>
    <div class="db-kpi-hint">{!! $hint !!}</div>
@if($href)
        <i class="ti ti-arrow-up-right db-kpi-go"></i>
    </a>
@else
    </div>
@endif
