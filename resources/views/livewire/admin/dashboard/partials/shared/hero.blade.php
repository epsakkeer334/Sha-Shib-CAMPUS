{{-- Dashboard header (dashboards only — other pages keep the light page header).
     Params: title, user, line (HTML, escaped by caller), actions = [[label, route, icon, primary(bool)]] --}}
@php
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $greetIcon = $hour < 12 ? 'ti-sun' : ($hour < 17 ? 'ti-sun-high' : 'ti-moon-stars');
@endphp
<div class="db-banner mb-3">
    <span class="db-banner-glow"></span>
    <span class="db-banner-orb db-orb-1"></span>
    <span class="db-banner-orb db-orb-2"></span>
    <div class="db-banner-inner">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <span class="db-banner-av">@if($user->user_image)<img src="{{ $user->user_image_url }}" alt="{{ $user->name }}">@else{{ $initials }}@endif</span>
            <div class="min-w-0">
                <div class="db-banner-greet"><i class="ti {{ $greetIcon }}"></i> {{ $greeting }}, <strong>{{ $user->name }}</strong></div>
                <h2 class="db-banner-title">{{ $title }}</h2>
                @if(!empty($line))<div class="db-banner-line">{!! $line !!}</div>@endif
                @if(!empty($actions))
                    <div class="db-banner-actions">
                        @foreach($actions as [$label, $route, $icon, $primary])
                            @if(\Illuminate\Support\Facades\Route::has($route))
                                <a href="{{ route($route) }}" class="db-bbtn {{ $primary ? 'is-primary' : '' }}"><i class="{{ $icon }}"></i> {{ $label }}</a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="db-banner-clock" data-loaded="{{ now()->toIso8601String() }}">
            <span class="db-live"><span class="db-live-dot"></span> Live</span>
            <div class="db-clock-time" data-clock>{{ now()->format('h:i:s A') }}</div>
            <div class="db-clock-date">{{ now()->format('l, d F Y') }}</div>
            <div class="db-clock-updated"><i class="ti ti-refresh"></i> Figures updated <span data-updated>just now</span> · <a href="{{ route('admin.dashboard') }}">refresh</a></div>
        </div>
    </div>
</div>
