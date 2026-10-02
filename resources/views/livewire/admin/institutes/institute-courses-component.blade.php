<div class="content">
    <!-- Page Header -->
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Institute Courses</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Institute Management</li>
                    @if($scopeInstituteName)
                        <li class="breadcrumb-item"><a href="{{ route('admin.institute-courses') }}">Institute Courses</a></li>
                        <li class="breadcrumb-item active">{{ $scopeInstituteName }}</li>
                    @else
                        <li class="breadcrumb-item active">Institute Courses</li>
                    @endif
                </ol>
            </nav>
        </div>

        <button type="button" wire:click="openModal" class="btn btn-primary d-flex align-items-center shadow-sm">
            <i class="ti ti-circle-plus me-2"></i> Assign Courses
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <livewire:admin.components.table.data-table
            :model-class="\App\Models\Admin\InstituteCourse::class"
            :columns="[
                ['label' => '#', 'field' => 'id', 'sortable' => true],
                ['label' => 'Institute', 'field' => 'institute.name', 'sortable' => false],
                ['label' => 'Institute Code', 'field' => 'institute.code', 'sortable' => false],
                ['label' => 'Course', 'field' => 'course.name', 'sortable' => false],
                ['label' => 'Course Code', 'field' => 'course.code', 'sortable' => false],
                ['label' => 'Status', 'field' => 'status', 'type' => 'status', 'sortable' => true],
                ['label' => 'Actions', 'field' => 'actions', 'type' => 'actions', 'actions' => ['edit', 'delete']],
            ]"
            :filters="['All' => 'All', 'Active' => 'Active', 'Inactive' => 'Inactive']"
            :extra-filters="$scopeInstituteId ? [['field' => 'institute_id', 'value' => $scopeInstituteId]] : []"
            title="{{ $scopeInstituteName ? 'Courses of ' . $scopeInstituteName : 'Courses Offered by Institutes' }}"
        />
    </div>

    <!-- Assign / Edit Modal -->
    <div class="modal fade" id="instituteCourseModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-primary bg-opacity-10 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-primary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="ti ti-books text-primary fs-14"></i>
                        </div>
                        <div>
                            <h4 class="modal-title fw-semibold mb-0">{{ $isEdit ? 'Edit Course Offering' : 'Assign Courses to Institute' }}</h4>
                            <small class="text-muted">Students can only join active courses of their institute</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="card border-0 bg-light bg-opacity-50 mb-3">
                        <div class="card-body p-3">
                            @if($isEdit && $editing)
                                <dl class="row mb-0 small">
                                    <dt class="col-sm-3">Institute</dt>
                                    <dd class="col-sm-9">{{ $editing->institute->name ?? '—' }} ({{ $editing->institute->code ?? '' }})</dd>
                                    <dt class="col-sm-3">Course</dt>
                                    <dd class="col-sm-9 mb-0">{{ $editing->course->name ?? '—' }} ({{ $editing->course->code ?? '' }})</dd>
                                </dl>
                            @else
                                <div class="mb-3">
                                    <label class="form-label fw-medium small">Institute <span class="text-danger">*</span></label>
                                    @if($scopeInstituteId)
                                        <input type="text" class="form-control bg-light" value="{{ $scopeInstituteName }}" readonly>
                                    @else
                                        <select class="form-select @error('institute_id') is-invalid @enderror" wire:model="institute_id">
                                            <option value="">Select institute</option>
                                            @foreach($institutes as $institute)
                                                <option value="{{ $institute->id }}">{{ $institute->name }} ({{ $institute->code }})</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @error('institute_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>

                                <label class="form-label fw-medium small">Courses <span class="text-danger">*</span></label>
                                @if(!$institute_id)
                                    <p class="text-muted small mb-0">Select an institute first.</p>
                                @elseif($availableCourses->isEmpty())
                                    <p class="text-muted small mb-0">
                                        This institute already offers every active course. Add new courses in Master Data → Academic → Courses.
                                    </p>
                                @else
                                    <div class="row g-2">
                                        @foreach($availableCourses as $course)
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="course{{ $course->id }}"
                                                           value="{{ $course->id }}" wire:model.defer="course_ids">
                                                    <label class="form-check-label" for="course{{ $course->id }}">
                                                        {{ $course->name }} <span class="text-muted">({{ $course->code }})</span>
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @error('course_ids') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @endif
                        </div>
                    </div>

                    <!-- Status (bottom of form) -->
                    <div class="card border-0 bg-light bg-opacity-50">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <i class="ti ti-toggle-right text-primary me-2 fs-14"></i>
                                <div>
                                    <h6 class="fw-semibold mb-0">Status</h6>
                                    <small class="text-muted">Inactive = not offered to new students</small>
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="instituteCourseStatus" wire:model="status" value="1">
                                <label class="form-check-label fw-medium" for="instituteCourseStatus">{{ $status ? 'Active' : 'Inactive' }}</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top">
                    <button class="btn btn-light px-4 me-2" wire:click="closeModal">
                        <i class="ti ti-x me-1 fs-16"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary px-4" wire:click="save" wire:loading.attr="disabled">
                        <i class="ti ti-device-floppy me-1 fs-16"></i> {{ $isEdit ? 'Update' : 'Assign Courses' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="instituteCourseDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Remove Course</h5>
                    <p class="text-muted mb-4">Remove this course from the institute? You can assign it again later.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" wire:click="delete">Yes, Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('livewire:load', function () {
    [
        { name: 'institute-course', id: 'instituteCourseModal' },
        { name: 'institute-course-delete', id: 'instituteCourseDeleteModal' },
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
