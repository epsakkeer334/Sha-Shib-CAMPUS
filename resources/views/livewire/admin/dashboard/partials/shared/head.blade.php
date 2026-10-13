{{-- Dashboard page header. Params: title --}}
<div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
    <div class="my-auto mb-2">
        <h2 class="mb-1">{{ $title }}</h2>
        <nav>
            <ol class="breadcrumb mb-0 sl-crumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                <li class="breadcrumb-item">Dashboard</li>
                <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
        <span class="btn btn-white d-inline-flex align-items-center mb-2 me-2" title="Figures as of now">
            <i class="ti ti-calendar text-gray-9 me-1"></i>{{ now()->format('l, d M Y') }}
        </span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-white d-inline-flex align-items-center mb-2" title="Refresh">
            <i class="ti ti-refresh me-1"></i>Refresh
        </a>
    </div>
</div>
