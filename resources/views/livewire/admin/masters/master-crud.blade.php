<div class="content">
    <!-- Page Header -->
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">{{ $title }}</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Master Data</li>
                    @if($group !== 'Master Data')
                        <li class="breadcrumb-item">{{ $group }}</li>
                    @endif
                    <li class="breadcrumb-item active">{{ $title }}</li>
                </ol>
            </nav>
        </div>

        <button type="button" wire:click="openModal" class="btn btn-primary d-flex align-items-center shadow-sm">
            <i class="ti ti-circle-plus me-2"></i> Add {{ $singular }}
        </button>
    </div>

    <!-- List -->
    <div class="card shadow-sm border-0">
        <livewire:admin.components.table.data-table
            :model-class="$modelClass"
            :columns="$columns"
            :filters="['All' => 'All', 'Active' => 'Active', 'Inactive' => 'Inactive']"
            title="{{ $title }} List"
        />
    </div>

    @include('livewire.admin.masters.partials.master-modals')
</div>

@include('livewire.admin.masters.partials.master-modals-assets')
