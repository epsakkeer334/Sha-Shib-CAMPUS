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
                ['label' => 'Actions', 'field' => 'actions', 'type' => 'actions', 'actions' => ['edit', 'delete', ['route' => 'admin.institute-users.institute', 'parameter' => 'institute_id', 'parameter_value' => 'id', 'icon' => 'ti ti-users', 'class' => 'btn-outline-info', 'label' => 'Users', 'show_label' => false]]]
            ]"
            :filters="['All' => 'All', 'Active' => 'Active', 'Inactive' => 'Inactive']"
            title="Institutes List"
        />
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="instituteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h4 class="modal-title fw-semibold">{{ $isEdit ? 'Edit Institute' : 'Add Institute' }}</h4>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Basic Information Section -->
                        <div class="col-12">
                            <h5 class="fw-semibold mb-3">Basic Information</h5>
                            <hr class="mt-0">
                        </div>

                        <!-- Institute Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Institute Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder="Enter institute name">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- Institute Code -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Institute Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder="e.g., INST001">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Status</label>
                            <select class="form-select @error('status') is-invalid @enderror" wire:model="status">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" wire:model="description" rows="3"
                                      placeholder="Enter institute description"></textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- Address Section -->
                        <div class="col-12 mt-3">
                            <h5 class="fw-semibold mb-3">Address Information</h5>
                            <hr class="mt-0">
                        </div>

                        <!-- Address -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Address</label>
                            <textarea class="form-control @error('address') is-invalid @enderror" wire:model="address" rows="2"
                                      placeholder="Enter street address"></textarea>
                            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- City -->
                        <div class="col-md-4">
                            <label class="form-label fw-medium">City</label>
                            <input type="text" class="form-control @error('city') is-invalid @enderror" wire:model="city" placeholder="City">
                            @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- Country -->
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Country <span class="text-danger">*</span></label>
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
                            <label class="form-label fw-medium">State <span class="text-danger">*</span></label>
                            <select class="form-select @error('state_id') is-invalid @enderror" wire:model="state_id" {{ empty($states) ? 'disabled' : '' }}>
                                <option value="">Select State</option>
                                @foreach($states as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('state_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if(empty($states) && $country_id)
                                <small class="text-muted">No states available for selected country</small>
                            @endif
                        </div>

                        <!-- Postal Code -->
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Postal Code</label>
                            <input type="text" class="form-control @error('postal_code') is-invalid @enderror" wire:model="postal_code" placeholder="Postal code">
                            @error('postal_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- Contact Information Section -->
                        <div class="col-12 mt-3">
                            <h5 class="fw-semibold mb-3">Contact Information</h5>
                            <hr class="mt-0">
                        </div>

                        <!-- Contact Person -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Contact Person</label>
                            <input type="text" class="form-control @error('contact_person') is-invalid @enderror" wire:model="contact_person" placeholder="Contact person name">
                            @error('contact_person') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" wire:model="email" placeholder="institute@example.com">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control @error('phone') is-invalid @enderror" wire:model="phone" placeholder="Phone number">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- Website -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Website</label>
                            <input type="url" class="form-control @error('website') is-invalid @enderror" wire:model="website" placeholder="https://example.com">
                            @error('website') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- Media Section -->
                        <div class="col-12 mt-3">
                            <h5 class="fw-semibold mb-3">Media</h5>
                            <hr class="mt-0">
                        </div>

                        <!-- Logo Upload -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Institute Logo <span class="text-danger">*</span></label>
                            <input type="file" class="form-control @error('logo') is-invalid @enderror" wire:model="logo" accept="image/*">
                            @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror

                            <div class="mt-2">
                                @if ($logo && !is_string($logo))
                                    <div class="mb-2">
                                        <label class="form-label fw-medium small">New Logo Preview:</label>
                                        <img src="{{ $logo->temporaryUrl() }}" class="img-thumbnail" style="max-height: 100px; max-width: 200px;">
                                    </div>
                                @elseif($isEdit && $recordId)
                                    @php $institute = \App\Models\Admin\Institute::find($recordId) @endphp
                                    @if($institute && $institute->logo)
                                        <div>
                                            <label class="form-label fw-medium small">Current Logo:</label>
                                            <img src="{{ $institute->logo_url }}" class="img-thumbnail" style="max-height: 100px; max-width: 200px;">
                                            <div class="alert alert-info py-2 small mt-2">
                                                <i class="ti ti-info-circle me-1"></i>
                                                Upload a new logo to replace the current one (optional)
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    <div class="alert alert-info py-2 small mt-2">
                                        <i class="ti ti-info-circle me-1"></i>
                                        Upload an institute logo (Recommended: 200x200 pixels)
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Banner Upload -->
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Institute Banner</label>
                            <input type="file" class="form-control @error('banner') is-invalid @enderror" wire:model="banner" accept="image/*">
                            @error('banner') <div class="invalid-feedback">{{ $message }}</div> @enderror

                            <div class="mt-2">
                                @if ($banner && !is_string($banner))
                                    <div class="mb-2">
                                        <label class="form-label fw-medium small">New Banner Preview:</label>
                                        <img src="{{ $banner->temporaryUrl() }}" class="img-thumbnail" style="max-height: 150px; width: 100%; object-fit: cover;">
                                    </div>
                                @elseif($isEdit && $recordId)
                                    @php $institute = \App\Models\Admin\Institute::find($recordId) @endphp
                                    @if($institute && $institute->banner)
                                        <div>
                                            <label class="form-label fw-medium small">Current Banner:</label>
                                            <img src="{{ $institute->banner_url }}" class="img-thumbnail" style="max-height: 150px; width: 100%; object-fit: cover;">
                                            <div class="alert alert-info py-2 small mt-2">
                                                <i class="ti ti-info-circle me-1"></i>
                                                Leave empty to keep current banner
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <!-- About Section -->
                        <div class="col-12 mt-3">
                            <h5 class="fw-semibold mb-3">About Institute</h5>
                            <hr class="mt-0">
                        </div>

                        <!-- About with Summernote Editor -->
                        <div class="col-12">
                            <label class="form-label fw-medium">About Institute</label>
                            <div wire:ignore>
                                <textarea id="summernote" class="form-control @error('about') is-invalid @enderror"
                                          rows="10" placeholder="Enter detailed information about the institute"></textarea>
                            </div>
                            @error('about') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="text-muted mt-1">Use the toolbar to format text, add images, links, and more.</small>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top-0">
                    <button class="btn btn-light px-4" wire:click="closeModal">Cancel</button>
                    <button type="button" class="btn btn-primary px-4" onclick="saveInstitute()">
                        <i class="ti ti-device-floppy me-1"></i> {{ $isEdit ? 'Update' : 'Save' }}
                    </button>
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
