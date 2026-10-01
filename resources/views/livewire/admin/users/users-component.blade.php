<div class="content">
    <!-- Page Header -->
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">{{ $scopeInstituteName ? 'Institute Users' : 'Users' }}</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Administration</li>
                    @if($scopeInstituteName)
                        <li class="breadcrumb-item"><a href="{{ route('admin.institutes') }}">Institutes</a></li>
                        <li class="breadcrumb-item active">{{ $scopeInstituteName }}</li>
                    @else
                        <li class="breadcrumb-item active">Users</li>
                    @endif
                </ol>
            </nav>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-3">
            @can('users.create')
                <button type="button" wire:click="openModal" class="btn btn-primary d-flex align-items-center shadow-sm">
                    <i class="ti ti-circle-plus me-2"></i> Add User
                </button>
            @endcan
        </div>
    </div>

    <!-- Users table -->
    <div class="card shadow-sm border-0">
        <livewire:admin.components.table.data-table
            :model-class="\App\Models\User::class"
            :columns="[
                ['label' => '#', 'field' => 'id', 'sortable' => true],
                ['label' => 'Name', 'field' => 'name', 'sortable' => true],
                ['label' => 'Email', 'field' => 'email', 'sortable' => true],
                ['label' => 'Phone', 'field' => 'phone', 'sortable' => false],
                ['label' => 'Employee Code', 'field' => 'employee_code', 'sortable' => true],
                ['label' => 'Role', 'field' => 'role_html', 'type' => 'html', 'sortable' => false],
                ['label' => 'Institute', 'field' => 'institute.name', 'sortable' => false],
                ['label' => 'Status', 'field' => 'status', 'type' => 'status', 'sortable' => true],
                ['label' => 'Last Login', 'field' => 'formatted_last_login', 'sortable' => false],
                ['label' => 'Actions', 'field' => 'actions', 'type' => 'actions', 'actions' => ['edit', 'delete']],
            ]"
            :filters="['All' => 'All', 'Active' => 'Active', 'Inactive' => 'Inactive']"
            :extra-filters="$scopeInstituteId ? [['field' => 'institute_id', 'value' => $scopeInstituteId]] : []"
            title="{{ $scopeInstituteName ? 'Users of ' . $scopeInstituteName : 'Users List' }}"
        />
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-primary bg-opacity-10 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-primary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="ti ti-user-plus text-primary fs-14"></i>
                        </div>
                        <div>
                            <h4 class="modal-title fw-semibold mb-0">{{ $isEdit ? 'Edit User' : 'Add New User' }}</h4>
                            <small class="text-muted">{{ $isEdit ? 'Update the user details below' : 'Create a login and assign a role' }}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Section: Role & Institute -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-shield text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Role & Institute</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Role <span class="text-danger">*</span></label>
                                    <select class="form-select @error('role') is-invalid @enderror" wire:model="role" {{ $editingSelf ? 'disabled' : '' }}>
                                        <option value="">Select role</option>
                                        @foreach($roleOptions as $slug => $label)
                                            <option value="{{ $slug }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    @if($editingSelf)
                                        <small class="text-muted">You cannot change your own role.</small>
                                    @endif
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">
                                        Institute @if($role !== 'super-admin')<span class="text-danger">*</span>@endif
                                    </label>
                                    @if($role === 'super-admin')
                                        <input type="text" class="form-control bg-light" value="All institutes (central user)" readonly>
                                    @elseif($isSuperAdmin && !$editingSelf)
                                        <select class="form-select @error('institute_id') is-invalid @enderror" wire:model="institute_id">
                                            <option value="">Select institute</option>
                                            @foreach($instituteOptions as $institute)
                                                <option value="{{ $institute->id }}">{{ $institute->name }} ({{ $institute->code }})</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" class="form-control bg-light" readonly
                                               value="{{ optional($instituteOptions->firstWhere('id', $institute_id))->name ?? '—' }}">
                                    @endif
                                    @error('institute_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Personal Details -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-user text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">User Details</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model.defer="name" placeholder="Full name">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Employee Code</label>
                                    <input type="text" class="form-control @error('employee_code') is-invalid @enderror" wire:model.defer="employee_code" placeholder="Optional">
                                    @error('employee_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Email (login) <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" wire:model.defer="email" placeholder="user@example.com">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Phone</label>
                                    <input type="tel" class="form-control @error('phone') is-invalid @enderror" wire:model.defer="phone" placeholder="Phone number">
                                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Password -->
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ti ti-key text-primary me-2 fs-14"></i>
                                <h6 class="fw-semibold mb-0">Password</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Password @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" wire:model.defer="password"
                                           placeholder="{{ $isEdit ? 'Leave blank to keep current password' : 'Minimum 8 characters' }}" autocomplete="new-password">
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Confirm Password</label>
                                    <input type="password" class="form-control" wire:model.defer="password_confirmation" autocomplete="new-password">
                                </div>
                            </div>
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
                                        <small class="text-muted">Inactive users cannot log in</small>
                                    </div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="userStatusSwitch"
                                           wire:model="status" value="1" {{ $editingSelf ? 'disabled' : '' }}>
                                    <label class="form-check-label fw-medium" for="userStatusSwitch">{{ $status ? 'Active' : 'Inactive' }}</label>
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
                            <i class="ti ti-device-floppy me-1 fs-16"></i> {{ $isEdit ? 'Update User' : 'Save User' }}
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
    <div class="modal fade" id="userDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Confirm Delete</h5>
                    <p class="text-muted mb-4">Are you sure you want to delete this user? They will no longer be able to log in.</p>
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
        { name: 'user', id: 'userModal' },
        { name: 'user-delete', id: 'userDeleteModal' },
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
