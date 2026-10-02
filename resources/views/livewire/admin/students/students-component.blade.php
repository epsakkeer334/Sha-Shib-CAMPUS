<div class="content">
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Students</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Student Onboarding</li>
                    <li class="breadcrumb-item active">Students</li>
                </ol>
            </nav>
        </div>
        @can('students.create')
            <a href="{{ route('admin.students.create') }}" class="btn btn-primary d-flex align-items-center shadow-sm">
                <i class="ti ti-circle-plus me-2"></i> Add Student
            </a>
        @endcan
    </div>

    <div class="card shadow-sm border-0">
        <livewire:admin.components.table.data-table
            :model-class="\App\Models\Admin\Student::class"
            :columns="$columns"
            :filters="$statusFilters"
            filter-field="status"
            sort-field="id"
            sort-direction="desc"
            title="Students List"
        />
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="studentDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Delete Student</h5>
                    <p class="text-muted mb-4">Only draft students can be deleted. Their uploaded documents are removed too.</p>
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
    window.addEventListener('open-student-delete-modal', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('studentDeleteModal')).show();
    });
    window.addEventListener('close-student-delete-modal', () => {
        const instance = bootstrap.Modal.getInstance(document.getElementById('studentDeleteModal'));
        if (instance) {
            instance.hide();
        }
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
    });
});
</script>
