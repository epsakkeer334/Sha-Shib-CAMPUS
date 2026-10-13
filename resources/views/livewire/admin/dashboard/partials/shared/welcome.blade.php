{{-- Welcome card. Params: user, line (HTML, already escaped), actions = [[label, route, icon, class]] --}}
@php
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp
<div class="card border-0">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap pb-1">
        <div class="d-flex align-items-center mb-3">
            @if($user->user_image)
                <span class="avatar avatar-xl flex-shrink-0"><img src="{{ $user->user_image_url }}" class="rounded-circle" alt="{{ $user->name }}"></span>
            @else
                <span class="avatar avatar-xl flex-shrink-0 rounded-circle bg-primary text-white fs-20 fw-bold">{{ $initials }}</span>
            @endif
            <div class="ms-3">
                <h3 class="mb-2">{{ $greeting }}, {{ $user->name }}</h3>
                <p class="mb-0">{!! $line !!}</p>
            </div>
        </div>
        <div class="d-flex align-items-center flex-wrap mb-1">
            @foreach($actions ?? [] as [$label, $route, $icon, $class])
                @if(\Illuminate\Support\Facades\Route::has($route))
                    <a href="{{ route($route) }}" class="btn {{ $class }} btn-md me-2 mb-2"><i class="{{ $icon }} me-1"></i>{{ $label }}</a>
                @endif
            @endforeach
        </div>
    </div>
</div>
