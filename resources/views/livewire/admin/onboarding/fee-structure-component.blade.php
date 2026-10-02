<div class="content">
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Fee structure</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Student Onboarding</li>
                    <li class="breadcrumb-item active">Fee structure</li>
                </ol>
            </nav>
        </div>
        @if($courseId)
            <button type="button" class="btn btn-primary" wire:click="create"><i class="ti ti-circle-plus me-1"></i> Add fee</button>
        @endif
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body row g-3">
            @if($isSuperAdmin)
                @include('livewire.admin.students.partials.select', ['name' => 'instituteId', 'label' => 'Institute', 'live' => true,
                    'options' => $institutes->mapWithKeys(fn ($i) => [$i->id => "{$i->name} ({$i->code})"])])
            @else
                <div class="col-md-6">
                    <label class="form-label fw-medium small">Institute</label>
                    <input type="text" class="form-control bg-light" readonly value="{{ optional($institutes->first())->name }}">
                </div>
            @endif
            @include('livewire.admin.students.partials.select', ['name' => 'courseId', 'label' => 'Course', 'live' => true,
                'options' => $courses->mapWithKeys(fn ($c) => [$c->id => "{$c->name} ({$c->code})"]),
                'placeholder' => $instituteId ? ($courses->isEmpty() ? 'No courses assigned to this institute' : 'Select course') : 'Select institute first'])
        </div>
    </div>

    @if($courseId)
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="fw-semibold mb-0">Fees for this course</h5>
                <span class="text-muted">Total (active) <b class="amount text-dark ms-1">{{ money_inr($total, false) }}</b></span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Fee</th><th class="text-end">Amount</th><th>Due</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($fees as $fee)
                            <tr>
                                <td class="text-muted">{{ $fee->sort_order }}</td>
                                <td class="fw-semibold">{{ $fee->fee_head }}</td>
                                <td class="text-end amount">{{ money_inr($fee->amount, false) }}</td>
                                <td>{{ $fee->due_days ? $fee->due_days . ' days after joining' : 'On joining' }}</td>
                                <td><span class="badge badge-soft-{{ $fee->status ? 'success' : 'danger' }}">{{ $fee->status ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-warning" wire:click="edit({{ $fee->id }})"><i class="ti ti-edit"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $fee->id }})"
                                            onclick="confirm('Remove this fee from the structure?') || event.stopImmediatePropagation()"><i class="ti ti-trash"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No fees yet for this course.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="modal fade" id="feeStructureModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ $editingId ? 'Edit fee' : 'Add fee' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        @include('livewire.admin.students.partials.input', ['name' => 'fee_head', 'label' => 'Fee', 'required' => true, 'col' => 12, 'placeholder' => 'e.g. Admission fee'])
                        @include('livewire.admin.students.partials.input', ['name' => 'amount', 'label' => 'Amount (₹)', 'type' => 'number', 'step' => '0.01', 'required' => true])
                        @include('livewire.admin.students.partials.input', ['name' => 'due_days', 'label' => 'Due (days after joining)', 'type' => 'number', 'required' => true, 'help' => '0 = due on the joining date'])
                        @include('livewire.admin.students.partials.input', ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'required' => true])
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="feeStatus" wire:model="status" value="1">
                                <label class="form-check-label" for="feeStatus">{{ $status ? 'Active' : 'Inactive' }}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="save">Save</button>
                </div>
            </div>
        </div>
    </div>

    @include('livewire.admin.onboarding.partials.styles')
</div>

<script>
document.addEventListener('livewire:load', function () {
    window.addEventListener('open-fee-structure-modal', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('feeStructureModal')).show());
    window.addEventListener('close-fee-structure-modal', () => {
        const instance = bootstrap.Modal.getInstance(document.getElementById('feeStructureModal'));
        if (instance) {
            instance.hide();
        }
    });
});
</script>
