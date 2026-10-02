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

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="masterModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-primary bg-opacity-10 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-primary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="{{ $icon }} text-primary fs-14"></i>
                        </div>
                        <div>
                            <h4 class="modal-title fw-semibold mb-0">{{ $isEdit ? 'Edit ' . $singular : 'Add New ' . $singular }}</h4>
                            <small class="text-muted">Master data — available to all institutes</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Section: Details -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-info-circle text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">{{ $singular }} Details</h6>
                            </div>
                            <div class="row g-3">
                                @foreach($fields as $key => $field)
                                    @php $required = in_array('required', $field['rules'], true); @endphp
                                    <div class="col-md-{{ $field['col'] ?? 6 }}">
                                        <label class="form-label fw-medium small">
                                            {{ $field['label'] }} @if($required)<span class="text-danger">*</span>@endif
                                        </label>

                                        @switch($field['type'] ?? 'text')
                                            @case('select')
                                                <select class="form-select @error('form.' . $key) is-invalid @enderror" wire:model.defer="form.{{ $key }}">
                                                    <option value="">Select {{ strtolower($field['label']) }}</option>
                                                    @foreach($field['options'] as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @break
                                            @case('textarea')
                                                <textarea class="form-control @error('form.' . $key) is-invalid @enderror" wire:model.defer="form.{{ $key }}" rows="2"
                                                          placeholder="{{ $field['placeholder'] ?? '' }}"></textarea>
                                                @break
                                            @default
                                                <input type="{{ $field['type'] ?? 'text' }}" class="form-control @error('form.' . $key) is-invalid @enderror"
                                                       wire:model.defer="form.{{ $key }}" placeholder="{{ $field['placeholder'] ?? '' }}">
                                        @endswitch

                                        @error('form.' . $key) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        @isset($field['help'])
                                            <small class="text-muted">{{ $field['help'] }}</small>
                                        @endisset
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Section: Status (bottom of form) -->
                    <div class="card border-0 bg-light bg-opacity-50">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-toggle-right text-primary me-2 fs-14"></i>
                                    <div>
                                        <h6 class="fw-semibold mb-0">Status</h6>
                                        <small class="text-muted">Inactive entries are hidden from dropdowns but kept on existing records</small>
                                    </div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="masterStatusSwitch" wire:model="status" value="1">
                                    <label class="form-check-label fw-medium" for="masterStatusSwitch">{{ $status ? 'Active' : 'Inactive' }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top">
                    <button class="btn btn-light px-4 me-2" wire:click="closeModal">
                        <i class="ti ti-x me-1 fs-16"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary px-4" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">
                            <i class="ti ti-device-floppy me-1 fs-16"></i> {{ $isEdit ? 'Update' : 'Save' }} {{ $singular }}
                        </span>
                        <span wire:loading wire:target="save">
                            <span class="spinner-border spinner-border-sm me-1"></span> Saving...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="masterDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Confirm Delete</h5>
                    <p class="text-muted mb-4">Delete this {{ strtolower($singular) }}? Entries already in use cannot be deleted — set them Inactive instead.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" wire:click="delete">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('livewire:load', function () {
    [
        { name: 'master', id: 'masterModal' },
        { name: 'master-delete', id: 'masterDeleteModal' },
    ].forEach(config => {
        window.addEventListener(`open-${config.name}-modal`, () => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById(config.id)).show();
        });

        window.addEventListener(`close-${config.name}-modal`, () => {
            const instance = bootstrap.Modal.getInstance(document.getElementById(config.id));
            if (instance) {
                instance.hide();
            }
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
        });
    });
});
</script>
