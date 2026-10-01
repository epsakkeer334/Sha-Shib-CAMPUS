{{-- Home for every role except Super Admin: quick links = the user's side menu (config/menu.php). --}}
@php
    $user = auth()->user();
    $sections = collect(\App\Services\MenuService::flatFor($user))
        ->map(fn ($section) => array_merge($section, [
            'items' => array_values(array_filter($section['items'], fn ($item) => $item['route'] !== 'admin.dashboard')),
        ]))
        ->filter(fn ($section) => count($section['items']) > 0);
@endphp

<div class="content">
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Welcome, {{ $user->name }}</h2>
            <p class="text-muted mb-0">
                {{ $user->role_display_name }}
                @if($user->institute) · {{ $user->institute->name }} ({{ $user->institute->code }}) @endif
            </p>
        </div>
    </div>

    @if($sections->isEmpty())
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5">
                <i class="ti ti-tools fs-1 text-muted"></i>
                <h5 class="mt-3">Your workspace is being prepared</h5>
                <p class="text-muted mb-0">
                    The screens for your role will appear here and in the side menu as each module goes live.
                </p>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach($sections as $section)
                <div class="col-md-6 col-xl-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3">{{ $section['title'] }}</h6>
                            <div class="d-flex gap-2 flex-wrap">
                                @foreach($section['items'] as $item)
                                    <a href="{{ route($item['route']) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="{{ $item['icon'] }} me-1"></i>{{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
