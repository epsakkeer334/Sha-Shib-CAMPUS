<div class="content">
    <!-- Page Header -->
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Institutes Management</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="#"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Admin</li>
                    <li class="breadcrumb-item">General Management</li>
                    <li class="breadcrumb-item active">Institutes</li>
                </ol>
            </nav>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-3">
            <button type="button" wire:click="openModal" class="btn btn-primary d-flex align-items-center shadow-sm">
                <i class="ti ti-circle-plus me-2"></i> Add Institute
            </button>

            <a href="javascript:void(0);" class="btn btn-outline-light border d-flex align-items-center justify-content-center p-2"
               data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse Header" id="collapse-header">
                <i class="ti ti-chevrons-up text-secondary"></i>
            </a>
        </div>
    </div>

    <!-- Data Table using common component -->
    <div class="card shadow-sm border-0">
        <livewire:admin.components.table.data-table
            :model-class="\App\Models\Admin\Institute::class"
            :columns="[
                ['label' => '#', 'field' => 'id', 'sortable' => true],
                ['label' => 'Logo', 'field' => 'logo_url', 'type' => 'image-url', 'sortable' => false],
                ['label' => 'Institute Name', 'field' => 'name', 'sortable' => true],
                ['label' => 'Code', 'field' => 'code', 'sortable' => true],
                ['label' => 'City', 'field' => 'city', 'sortable' => true],
                ['label' => 'State', 'field' => 'state.name', 'sortable' => false],
                ['label' => 'Country', 'field' => 'country.name', 'sortable' => false],
                ['label' => 'Contact Person', 'field' => 'contact_person', 'sortable' => true],
                ['label' => 'Email', 'field' => 'email', 'sortable' => false],
                ['label' => 'Phone', 'field' => 'phone', 'sortable' => false],
                ['label' => 'Status', 'field' => 'status', 'type' => 'status', 'sortable' => true],
                ['label' => 'Created At', 'field' => 'formatted_created_at', 'type' => 'datetime', 'format' => 'd M Y', 'sortable' => true],
                ['label' => 'Actions', 'field' => 'actions', 'type' => 'actions', 'actions' => ['edit', 'delete', ['route' => 'admin.institute-users.institute', 'parameter' => 'institute', 'parameter_value' => 'id', 'icon' => 'ti ti-users', 'class' => 'btn-outline-info', 'label' => 'Users', 'show_label' => false], ['route' => 'admin.institute-courses.institute', 'parameter' => 'institute', 'parameter_value' => 'id', 'icon' => 'ti ti-books', 'class' => 'btn-outline-primary', 'label' => 'Courses', 'show_label' => false]]]
            ]"
            :filters="['All' => 'All', 'Active' => 'Active', 'Inactive' => 'Inactive']"
            title="Institutes List"
        />
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="instituteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <!-- Header -->
                <div class="modal-header bg-primary bg-opacity-10 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-primary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="ti ti-building-bank text-primary fs-14"></i>
                        </div>
                        <div>
                            <h4 class="modal-title fw-semibold mb-0">{{ $isEdit ? 'Edit Institute' : 'Add New Institute' }}</h4>
                            <small class="text-muted">{{ $isEdit ? 'Update the institute details below' : 'Fill in the details to create a new institute' }}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>

                <!-- Body -->
                <div class="modal-body p-4">
                    <!-- Section: Basic Information -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-info-circle text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Basic Information</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Institute Name -->
                                <div class="col-md-5">
                                    <label class="form-label fw-medium small">Institute Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder="Enter institute name">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Code Prefix -->
                                <div class="col-md-2">
                                    <label class="form-label fw-medium small">Code Prefix @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
                                    <input type="text" class="form-control text-uppercase @error('code_prefix') is-invalid @elseif($this->prefixAvailable) is-valid @enderror" wire:model.debounce.300ms="code_prefix"
                                           maxlength="6" placeholder="e.g. SHA" autocomplete="off" @if($isEdit) readonly @endif style="font-family: 'IBM Plex Mono', ui-monospace, monospace; letter-spacing: .05em;">
                                    @error('code_prefix') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    @if(!$errors->has('code_prefix') && $this->prefixAvailable)
                                        <small class="text-success" style="font-size: 0.7rem;"><i class="ti ti-circle-check"></i> Available</small>
                                    @else
                                        <small class="text-muted" style="font-size: 0.7rem;">2–6 letters, unique</small>
                                    @endif
                                </div>

                                <!-- Established Year -->
                                <div class="col-md-2">
                                    <label class="form-label fw-medium small">Established Year <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control @error('established_year') is-invalid @enderror" wire:model="established_year"
                                        min="1800" max="{{ date('Y') }}" placeholder="e.g., {{ date('Y') }}">
                                    @error('established_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Institute Code -->
                                <div class="col-md-3">
                                    <label class="form-label fw-medium small">Institute Code</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ti ti-hash fs-14"></i></span>
                                        <input type="text" class="form-control bg-light" value="{{ $code }}" readonly
                                               placeholder="Auto-generated">
                                    </div>
                                    <small class="text-muted" style="font-size: 0.7rem;">
                                        {{ $isEdit ? 'Code cannot be changed.' : 'Prefix / established year / number. Final number is assigned on save.' }}
                                    </small>
                                </div>

                                <!-- Description -->
                                <div class="col-12">
                                    <label class="form-label fw-medium small">Description</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" wire:model="description" rows="2"
                                            placeholder="Brief description about the institute"></textarea>
                                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Address Information -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-map-pin text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Address Information</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Address -->
                                <div class="col-12">
                                    <label class="form-label fw-medium small">Street Address</label>
                                    <textarea class="form-control @error('address') is-invalid @enderror" wire:model="address" rows="2"
                                            placeholder="Enter street address"></textarea>
                                    @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Country -->
                                <div class="col-md-4">
                                    <label class="form-label fw-medium small">Country <span class="text-danger">*</span></label>
                                    <select class="form-select @error('country_id') is-invalid @enderror" wire:model="country_id">
                                        <option value="">Select Country</option>
                                        @foreach($countries as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('country_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- State -->
                                <div class="col-md-4">
                                    <label class="form-label fw-medium small">State <span class="text-danger">*</span></label>
                                    <select class="form-select @error('state_id') is-invalid @enderror" wire:model="state_id" {{ empty($states) ? 'disabled' : '' }}>
                                        <option value="">Select State</option>
                                        @foreach($states as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('state_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    @if(empty($states) && $country_id)
                                        <small class="text-muted" style="font-size: 0.7rem;">No states available</small>
                                    @endif
                                </div>

                                <!-- City -->
                                <div class="col-md-2">
                                    <label class="form-label fw-medium small">City</label>
                                    <input type="text" class="form-control @error('city') is-invalid @enderror" wire:model="city" placeholder="City">
                                    @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Postal Code -->
                                <div class="col-md-2">
                                    <label class="form-label fw-medium small">Postal Code</label>
                                    <input type="text" class="form-control @error('postal_code') is-invalid @enderror" wire:model="postal_code" placeholder="Postal">
                                    @error('postal_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Contact Information -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-phone text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Contact Information</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Contact Person -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Contact Person</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="ti ti-user text-muted fs-14"></i></span>
                                        <input type="text" class="form-control @error('contact_person') is-invalid @enderror" wire:model="contact_person" placeholder="Full name">
                                    </div>
                                    @error('contact_person') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Email -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Email <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="ti ti-mail text-muted fs-14"></i></span>
                                        <input type="email" class="form-control @error('email') is-invalid @enderror" wire:model="email" placeholder="institute@example.com">
                                    </div>
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Phone -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Phone <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="ti ti-phone text-muted fs-14"></i></span>
                                        <input type="tel" class="form-control @error('phone') is-invalid @enderror" wire:model="phone" placeholder="Phone number">
                                    </div>
                                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <!-- Website -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Website</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="ti ti-world text-muted fs-14"></i></span>
                                        <input type="url" class="form-control @error('website') is-invalid @enderror" wire:model="website" placeholder="https://example.com">
                                    </div>
                                    @error('website') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Media -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-photo text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Media</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Logo Upload -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Institute Logo <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control @error('logo') is-invalid @enderror" wire:model="logo" accept="image/*">
                                    @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                    <div class="mt-2">
                                        @if ($logo && !is_string($logo))
                                            <div class="d-flex align-items-center gap-2 p-2 bg-white rounded border">
                                                <img src="{{ $logo->temporaryUrl() }}" class="rounded" style="height: 60px; width: 60px; object-fit: cover;">
                                                <div>
                                                    <span class="badge bg-success bg-opacity-10 text-success small">New Logo</span>
                                                    <p class="mb-0 text-muted small">Preview ready</p>
                                                </div>
                                            </div>
                                        @elseif($isEdit && $recordId)
                                            @php $institute = \App\Models\Admin\Institute::find($recordId) @endphp
                                            @if($institute && $institute->logo)
                                                <div class="d-flex align-items-center gap-2 p-2 bg-white rounded border">
                                                    <img src="{{ $institute->logo_url }}" class="rounded" style="height: 60px; width: 60px; object-fit: cover;">
                                                    <div>
                                                        <span class="badge bg-info bg-opacity-10 text-info small">Current Logo</span>
                                                        <p class="mb-0 text-muted small">Upload new to replace</p>
                                                    </div>
                                                </div>
                                            @endif
                                        @else
                                            <div class="p-2 bg-white rounded border text-center">
                                                <i class="ti ti-cloud-upload text-muted fs-3"></i>
                                                <p class="mb-0 text-muted small">Recommended: 200x200px</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Banner Upload -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Institute Banner</label>
                                    <input type="file" class="form-control @error('banner') is-invalid @enderror" wire:model="banner" accept="image/*">
                                    @error('banner') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                    <div class="mt-2">
                                        @if ($banner && !is_string($banner))
                                            <div class="p-2 bg-white rounded border">
                                                <img src="{{ $banner->temporaryUrl() }}" class="rounded w-100" style="height: 60px; object-fit: cover;">
                                                <span class="badge bg-success bg-opacity-10 text-success small mt-1">New Banner</span>
                                            </div>
                                        @elseif($isEdit && $recordId)
                                            @php $institute = \App\Models\Admin\Institute::find($recordId) @endphp
                                            @if($institute && $institute->banner)
                                                <div class="p-2 bg-white rounded border">
                                                    <img src="{{ $institute->banner_url }}" class="rounded w-100" style="height: 60px; object-fit: cover;">
                                                    <span class="badge bg-info bg-opacity-10 text-info small mt-1">Current Banner</span>
                                                </div>
                                            @endif
                                        @else
                                            <div class="p-2 bg-white rounded border text-center">
                                                <i class="ti ti-photo text-muted fs-3"></i>
                                                <p class="mb-0 text-muted small">Recommended: 1200x400px</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: About Institute -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-file-description text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">About Institute</h6>
                            </div>
                            <div wire:ignore>
                                <textarea id="summernote" class="form-control @error('about') is-invalid @enderror"
                                        rows="10" placeholder="Enter detailed information about the institute"></textarea>
                            </div>
                            @error('about') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <small class="text-muted mt-1">Use the toolbar to format text, add images, links, and more.</small>
                        </div>
                    </div>

                    <!-- Section: Status -->
                    <div class="card border-0 bg-light bg-opacity-50">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-toggle-right text-primary me-2 fs-14"></i>
                                    <div>
                                        <h6 class="fw-semibold mb-0">Status</h6>
                                        <small class="text-muted">Set institute active or inactive</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="statusSwitch"
                                            wire:model="status" value="1" @if($status == 1) checked @endif>
                                        <label class="form-check-label fw-medium" for="statusSwitch">
                                            {{ $status == 1 ? 'Active' : 'Inactive' }}
                                        </label>
                                    </div>
                                    <span class="badge {{ $status == 1 ? 'bg-success' : 'bg-danger' }} bg-opacity-10 {{ $status == 1 ? 'text-success' : 'text-danger' }}">
                                        {{ $status == 1 ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer bg-light border-top">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <small class="text-muted">
                            <i class="ti ti-info-circle me-1 fs-16"></i>
                            Fields marked with <span class="text-danger">*</span> are required
                        </small>
                        <div>
                            <button class="btn btn-light px-4 me-2" wire:click="closeModal">
                                <i class="ti ti-x me-1 fs-16"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-primary px-4" onclick="saveInstitute()" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save,update">
                                    <i class="ti ti-device-floppy me-1 fs-16"></i> {{ $isEdit ? 'Update Institute' : 'Save Institute' }}
                                </span>
                                <span wire:loading wire:target="save,update">
                                    <span class="spinner-border spinner-border-sm me-1"></span> Saving...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Confirm Delete</h5>
                    <p class="text-muted mb-4">Are you sure you want to delete this institute? This action cannot be undone.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" wire:click="delete">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('livewire:load', function () {
    let summernoteInitialized = false;
    let currentContent = '';

    // Initialize Summernote
    function initSummernote(content = '') {
        if ($('#summernote').length && !summernoteInitialized) {
            $('#summernote').summernote({
                height: 300,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['fontname', ['fontname']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']],
                ],
                callbacks: {
                    onChange: function(contents) {
                        currentContent = contents;
                        @this.set('about', contents);
                    },
                    onInit: function() {
                        if (content) {
                            $('#summernote').summernote('code', content);
                            currentContent = content;
                            @this.set('about', content);
                        }
                    }
                }
            });
            summernoteInitialized = true;

            // Set content after initialization
            if (content) {
                setTimeout(() => {
                    $('#summernote').summernote('code', content);
                }, 100);
            }
        } else if (summernoteInitialized && content !== undefined && content !== currentContent) {
            $('#summernote').summernote('code', content);
            currentContent = content;
        }
    }

    // Destroy Summernote
    function destroySummernote() {
        if ($('#summernote').length && summernoteInitialized) {
            $('#summernote').summernote('destroy');
            summernoteInitialized = false;
            currentContent = '';
        }
    }

    // Reinitialize Summernote after Livewire updates
    function reinitSummernote() {
        if (summernoteInitialized) {
            destroySummernote();
        }
        setTimeout(() => {
            initSummernote(@this.about);
        }, 50);
    }

    // Save institute function
    window.saveInstitute = function() {
        if (@this.isEdit) {
            @this.call('update');
        } else {
            @this.call('save');
        }
    }

    // Modal management
    const modalConfigs = [
        { name: 'institute', id: 'instituteModal' },
        { name: 'delete', id: 'deleteModal' }
    ];

    modalConfigs.forEach(config => {
        window.addEventListener(`open-${config.name}-modal`, () => {
            const modal = new bootstrap.Modal(document.getElementById(config.id));
            modal.show();

            // Initialize Summernote when institute modal opens
            if (config.name === 'institute') {
                setTimeout(() => {
                    initSummernote(@this.about);
                }, 300);
            }
        });

        window.addEventListener(`close-${config.name}-modal`, () => {
            const modalElement = document.getElementById(config.id);
            const modalInstance = bootstrap.Modal.getInstance(modalElement);

            if (modalInstance) {
                modalInstance.hide();
            }

            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');

            // Destroy summernote when modal closes
            if (config.name === 'institute') {
                destroySummernote();
            }
        });
    });

    // Reinitialize Summernote when Livewire updates the about content
    window.addEventListener('init-summernote', (event) => {
        setTimeout(() => {
            initSummernote(event.detail.content);
        }, 100);
    });

    // Watch for Livewire updates that might affect the DOM
    Livewire.hook('message.processed', () => {
        if ($('#instituteModal').hasClass('show') && !summernoteInitialized) {
            setTimeout(() => {
                initSummernote(@this.about);
            }, 100);
        }
    });

    // Initialize tooltips
    const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipElements.forEach(element => {
        new bootstrap.Tooltip(element);
    });
});

// Additional listener for when modal is fully shown
document.addEventListener('shown.bs.modal', function (event) {
    if (event.target.id === 'instituteModal') {
        setTimeout(() => {
            if (typeof initSummernote === 'function' && !window.summernoteInitialized) {
                initSummernote(window.Livewire.find('institutes-component').get('about'));
            }
        }, 100);
    }
});
</script>
