@php
    $readOnly = !$editable || !$canEdit;
    $done = [
        'basic' => (bool) $student,
        'address' => $student && $student->hasAddressDetails(),
        'academic' => $student && $student->academicDetail,
        'documents' => $student && !isset($checklist['documents']),
        'review' => $student && $student->submitted_at,
    ];
    $tabs = [
        'basic' => ['Basic Details', 'ti ti-user'],
        'address' => ['Address & Parent', 'ti ti-map-pin'],
        'academic' => ['Academic Details', 'ti ti-school'],
        'documents' => ['KYC Documents', 'ti ti-file-certificate'],
        'review' => ['Review & Submit', 'ti ti-checklist'],
    ];
@endphp

<div class="content">
    <!-- Page Header -->
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">
                {{ $student ? $student->full_name : 'Add Student' }}
                @if($student) {!! $student->status_html !!} @endif
            </h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.students') }}">Students</a></li>
                    <li class="breadcrumb-item active">{{ $student ? 'Onboarding' : 'Add Student' }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($student)
                <span class="text-muted small">
                    {{ optional($student->institute)->name }} · Joining {{ $student->formatted_joining_date }} ·
                    Onboarding deadline <strong>{{ $student->formatted_onboarding_deadline }}</strong>
                </span>
            @endif
            <a href="{{ route('admin.students') }}" class="btn btn-light border"><i class="ti ti-arrow-left me-1"></i> Back to list</a>
        </div>
    </div>

    @if($student && !$editable)
        <div class="alert alert-info small"><i class="ti ti-lock me-1"></i> Onboarding is closed for this student ({{ $student->status_label }}). Details are read-only.</div>
    @endif

    <div class="card shadow-sm border-0">
        <!-- Tabs -->
        <div class="card-header bg-white border-bottom pb-0">
            <ul class="nav nav-tabs border-0 flex-nowrap overflow-auto">
                @foreach($tabs as $key => [$label, $icon])
                    @php $locked = !$student && $key !== 'basic'; @endphp
                    <li class="nav-item">
                        <a href="javascript:void(0);" wire:click="goTo('{{ $key }}')"
                           class="nav-link d-flex align-items-center text-nowrap {{ $activeTab === $key ? 'active fw-semibold' : '' }} {{ $locked ? 'disabled text-muted' : '' }}"
                           @if($locked) title="Save basic details first" @endif>
                            <span class="me-2">{{ $loop->iteration }}.</span>
                            <i class="{{ $icon }} me-1"></i> {{ $label }}
                            @if($done[$key])
                                <i class="ti ti-circle-check-filled text-success ms-2"></i>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body p-4">
            <fieldset @if($readOnly) disabled @endif>

            {{-- ============ 1. BASIC DETAILS ============ --}}
            @if($activeTab === 'basic')
                <h6 class="fw-semibold mb-3"><i class="ti ti-building text-primary me-1"></i> Institute & Course</h6>
                <div class="row g-3 mb-4">
                    @if($isSuperAdmin && !$student)
                        @include('livewire.admin.students.partials.select', ['name' => 'institute_id', 'label' => 'Institute', 'required' => true, 'live' => true,
                            'options' => $institutes->mapWithKeys(fn ($i) => [$i->id => "{$i->name} ({$i->code})"])])
                    @else
                        <div class="col-md-6">
                            <label class="form-label fw-medium small">Institute</label>
                            <input type="text" class="form-control bg-light" readonly
                                   value="{{ optional($institutes->firstWhere('id', $institute_id) ?? optional($student)->institute)->name }}">
                        </div>
                    @endif
                    @include('livewire.admin.students.partials.select', ['name' => 'course_id', 'label' => 'Course', 'required' => true,
                        'options' => $courses->mapWithKeys(fn ($c) => [$c->id => "{$c->name} ({$c->code})"]),
                        'placeholder' => $institute_id ? ($courses->isEmpty() ? 'No active courses at this institute' : 'Select course') : 'Select institute first',
                        'help' => $institute_id && $courses->isEmpty() ? 'Assign courses under Institute Management → Institute Courses.' : null])
                    @include('livewire.admin.students.partials.input', ['name' => 'joining_date', 'label' => 'Joining Date', 'type' => 'date', 'required' => true, 'col' => 3,
                        'help' => 'Onboarding must be completed within ' . config('camp.onboarding_days') . ' days.'])
                </div>

                <h6 class="fw-semibold mb-3"><i class="ti ti-user text-primary me-1"></i> Personal Details</h6>
                <div class="row g-3 mb-4">
                    @include('livewire.admin.students.partials.input', ['name' => 'first_name', 'label' => 'First Name', 'required' => true, 'col' => 4])
                    @include('livewire.admin.students.partials.input', ['name' => 'last_name', 'label' => 'Last Name', 'required' => true, 'col' => 4])
                    @include('livewire.admin.students.partials.input', ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'required' => true, 'col' => 2])
                    @include('livewire.admin.students.partials.select', ['name' => 'gender', 'label' => 'Gender', 'required' => true, 'col' => 2, 'options' => config('camp.genders')])
                    @include('livewire.admin.students.partials.select', ['name' => 'qualification_id', 'label' => 'Qualification', 'required' => true, 'col' => 4, 'options' => $qualifications])
                    @include('livewire.admin.students.partials.select', ['name' => 'religion_id', 'label' => 'Religion', 'required' => true, 'col' => 4, 'live' => true, 'options' => $religions])
                    @include('livewire.admin.students.partials.select', ['name' => 'category_id', 'label' => 'Category', 'required' => true, 'col' => 4, 'options' => $categories,
                        'placeholder' => $religion_id ? 'Select category' : 'Select religion first'])
                </div>

                <h6 class="fw-semibold mb-3"><i class="ti ti-phone text-primary me-1"></i> Contact</h6>
                <div class="row g-3">
                    @include('livewire.admin.students.partials.input', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'col' => 4, 'placeholder' => 'student@example.com'])
                    @include('livewire.admin.students.partials.input', ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'col' => 4, 'placeholder' => '+919876543210'])
                    @include('livewire.admin.students.partials.input', ['name' => 'emergency_contact', 'label' => 'Emergency Contact', 'type' => 'tel', 'required' => true, 'col' => 4])
                </div>

                @unless($readOnly)
                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-primary px-4" wire:click="saveBasic" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveBasic"><i class="ti ti-device-floppy me-1"></i> {{ $student ? 'Save & Continue' : 'Create Student & Continue' }}</span>
                            <span wire:loading wire:target="saveBasic"><span class="spinner-border spinner-border-sm me-1"></span> Saving...</span>
                        </button>
                    </div>
                @endunless
            @endif

            {{-- ============ 2. ADDRESS & PARENT ============ --}}
            @if($activeTab === 'address')
                <h6 class="fw-semibold mb-3"><i class="ti ti-map-pin text-primary me-1"></i> Address</h6>
                <div class="row g-3 mb-4">
                    @include('livewire.admin.students.partials.input', ['name' => 'address', 'label' => 'Street Address', 'type' => 'textarea', 'required' => true, 'col' => 12])
                    @include('livewire.admin.students.partials.select', ['name' => 'country_id', 'label' => 'Country', 'required' => true, 'col' => 3, 'live' => true, 'options' => $countries])
                    @include('livewire.admin.students.partials.select', ['name' => 'state_id', 'label' => 'State', 'required' => true, 'col' => 3, 'options' => $states,
                        'placeholder' => $country_id ? 'Select state' : 'Select country first'])
                    @include('livewire.admin.students.partials.input', ['name' => 'city', 'label' => 'City', 'required' => true, 'col' => 3])
                    @include('livewire.admin.students.partials.input', ['name' => 'pincode', 'label' => 'Pincode', 'required' => true, 'col' => 3])
                </div>

                <h6 class="fw-semibold mb-3"><i class="ti ti-users text-primary me-1"></i> Parent / Guardian</h6>
                <div class="row g-3">
                    @include('livewire.admin.students.partials.input', ['name' => 'parent_name', 'label' => 'Parent Name', 'required' => true])
                    @include('livewire.admin.students.partials.input', ['name' => 'parent_occupation', 'label' => 'Occupation'])
                    @include('livewire.admin.students.partials.input', ['name' => 'parent_phone', 'label' => 'Parent Phone', 'type' => 'tel', 'required' => true])
                    @include('livewire.admin.students.partials.input', ['name' => 'parent_email', 'label' => 'Parent Email', 'type' => 'email'])
                </div>

                @unless($readOnly)
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-light border" wire:click="goTo('basic')"><i class="ti ti-arrow-left me-1"></i> Back</button>
                        <button type="button" class="btn btn-primary px-4" wire:click="saveAddress" wire:loading.attr="disabled">
                            <i class="ti ti-device-floppy me-1"></i> Save & Continue
                        </button>
                    </div>
                @endunless
            @endif

            {{-- ============ 3. ACADEMIC DETAILS ============ --}}
            @if($activeTab === 'academic')
                <h6 class="fw-semibold mb-3"><i class="ti ti-certificate text-primary me-1"></i> Matriculation (10th)</h6>
                <div class="row g-3 mb-4">
                    @include('livewire.admin.students.partials.select', ['name' => 'matriculation_board_id', 'label' => 'Board', 'required' => true, 'options' => $matriculationBoards])
                    @include('livewire.admin.students.partials.select', ['name' => 'matriculation_mark_type', 'label' => 'Mark In', 'required' => true, 'col' => 3, 'live' => true, 'options' => config('camp.mark_types')])
                    @include('livewire.admin.students.partials.input', ['name' => 'matriculation_mark', 'label' => 'Mark', 'type' => 'number', 'step' => '0.01', 'required' => true, 'col' => 3,
                        'placeholder' => $matriculation_mark_type === 'cgpa' ? '0 – 10' : '0 – 100'])
                </div>

                <h6 class="fw-semibold mb-3"><i class="ti ti-certificate-2 text-primary me-1"></i> Higher Secondary (12th)</h6>
                <div class="row g-3">
                    @include('livewire.admin.students.partials.select', ['name' => 'higher_secondary_board_id', 'label' => 'Board', 'required' => true, 'options' => $higherSecondaryBoards])
                    @include('livewire.admin.students.partials.select', ['name' => 'higher_secondary_subject', 'label' => 'Subject', 'required' => true, 'col' => 2, 'options' => config('camp.higher_secondary_subjects')])
                    @include('livewire.admin.students.partials.select', ['name' => 'higher_secondary_mark_type', 'label' => 'Mark In', 'required' => true, 'col' => 2, 'live' => true, 'options' => config('camp.mark_types')])
                    @include('livewire.admin.students.partials.input', ['name' => 'higher_secondary_mark', 'label' => 'Mark', 'type' => 'number', 'step' => '0.01', 'required' => true, 'col' => 2,
                        'placeholder' => $higher_secondary_mark_type === 'cgpa' ? '0 – 10' : '0 – 100'])
                </div>

                @unless($readOnly)
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-light border" wire:click="goTo('address')"><i class="ti ti-arrow-left me-1"></i> Back</button>
                        <button type="button" class="btn btn-primary px-4" wire:click="saveAcademic" wire:loading.attr="disabled">
                            <i class="ti ti-device-floppy me-1"></i> Save & Continue
                        </button>
                    </div>
                @endunless
            @endif

            {{-- ============ 4. KYC DOCUMENTS ============ --}}
            @if($activeTab === 'documents')
                <p class="text-muted small mb-3">
                    Files are stored privately and only visible to authorised staff. Documents are verified by the Institute Admin in the next step.
                </p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr><th style="width: 22%">Document</th><th>Uploaded file</th><th style="width: 34%">Upload</th></tr>
                        </thead>
                        <tbody>
                            @foreach($documentTypes as $type => [$label, $required, $mimes, $maxKb])
                                <tr>
                                    <td>
                                        <div class="fw-medium">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</div>
                                        <small class="text-muted">{{ strtoupper(str_replace(',', ', ', $mimes)) }} · max {{ $maxKb / 1024 }} MB</small>
                                    </td>
                                    <td>
                                        @forelse($documents->get($type, collect()) as $doc)
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                @if($doc->is_image)
                                                    <img src="{{ route('admin.students.documents.show', $doc->id) }}" class="rounded border" style="height: 40px; width: 40px; object-fit: cover;">
                                                @else
                                                    <i class="ti ti-file-type-pdf fs-4 text-danger"></i>
                                                @endif
                                                <div class="small">
                                                    <a href="{{ route('admin.students.documents.show', $doc->id) }}" target="_blank">{{ $doc->original_name }}</a>
                                                    <div class="text-muted">{{ $doc->size_label }} · {{ $doc->uploaded_at->format('d M Y') }} ·
                                                        <span class="badge badge-soft-{{ ['verified' => 'success', 'rejected' => 'danger'][$doc->verification_status] ?? 'warning' }}">{{ ucfirst($doc->verification_status) }}</span>
                                                    </div>
                                                </div>
                                                @unless($readOnly)
                                                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto" wire:click="confirmDeleteDocument({{ $doc->id }})" title="Remove">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                @endunless
                                            </div>
                                        @empty
                                            <span class="text-muted small">Not uploaded</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        @unless($readOnly)
                                            <div class="input-group input-group-sm">
                                                <input type="file" class="form-control @error('upload_' . $type) is-invalid @enderror" wire:model="upload_{{ $type }}"
                                                       accept="{{ collect(explode(',', $mimes))->map(fn ($m) => '.' . $m)->implode(',') }}">
                                                <button type="button" class="btn btn-primary" wire:click="uploadDocument('{{ $type }}')"
                                                        wire:loading.attr="disabled" wire:target="upload_{{ $type }},uploadDocument">
                                                    <i class="ti ti-upload"></i> {{ $documents->has($type) && $type !== 'other' ? 'Replace' : 'Upload' }}
                                                </button>
                                            </div>
                                            <div wire:loading wire:target="upload_{{ $type }}" class="small text-muted mt-1">Uploading…</div>
                                            @error('upload_' . $type) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-light border" wire:click="goTo('academic')"><i class="ti ti-arrow-left me-1"></i> Back</button>
                    <button type="button" class="btn btn-primary px-4" wire:click="goTo('review')">Continue to Review <i class="ti ti-arrow-right ms-1"></i></button>
                </div>
            @endif

            </fieldset>

            {{-- ============ 5. REVIEW & SUBMIT ============ --}}
            @if($activeTab === 'review' && $student)
                @php $academic = $student->academicDetail; @endphp

                @if($checklist)
                    <div class="alert alert-warning small">
                        <strong><i class="ti ti-alert-triangle me-1"></i> Still missing:</strong>
                        <ul class="mb-0 mt-1">
                            @foreach($checklist as $tab => $items)
                                @foreach($items as $item)
                                    <li>{{ $item }} <a href="javascript:void(0);" wire:click="goTo('{{ $tab }}')">Fix</a></li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="alert alert-success small"><i class="ti ti-circle-check me-1"></i> All details and required documents are in place.</div>
                @endif

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="card border h-100"><div class="card-body small">
                            <h6 class="fw-semibold">Basic Details</h6>
                            <dl class="row mb-0">
                                <dt class="col-5">Institute</dt><dd class="col-7">{{ optional($student->institute)->name }}</dd>
                                <dt class="col-5">Course</dt><dd class="col-7">{{ optional($student->course)->name }} ({{ optional($student->course)->code }})</dd>
                                <dt class="col-5">Name</dt><dd class="col-7">{{ $student->full_name }}</dd>
                                <dt class="col-5">Date of Birth</dt><dd class="col-7">{{ optional($student->dob)->format('d M Y') }}</dd>
                                <dt class="col-5">Gender</dt><dd class="col-7">{{ config('camp.genders.' . $student->gender) }}</dd>
                                <dt class="col-5">Qualification</dt><dd class="col-7">{{ optional($student->qualification)->name }}</dd>
                                <dt class="col-5">Religion / Category</dt><dd class="col-7">{{ optional($student->religion)->name }} / {{ optional($student->category)->name }}</dd>
                                <dt class="col-5">Email</dt><dd class="col-7">{{ $student->email }}</dd>
                                <dt class="col-5">Phone / Emergency</dt><dd class="col-7">{{ $student->phone }} / {{ $student->emergency_contact }}</dd>
                                <dt class="col-5">Joining / Deadline</dt><dd class="col-7">{{ $student->formatted_joining_date }} / {{ $student->formatted_onboarding_deadline }}</dd>
                            </dl>
                        </div></div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border h-100"><div class="card-body small">
                            <h6 class="fw-semibold">Address & Parent</h6>
                            <dl class="row mb-0">
                                <dt class="col-5">Address</dt><dd class="col-7">{{ $student->address ?: '—' }}</dd>
                                <dt class="col-5">City / Pincode</dt><dd class="col-7">{{ $student->city ?: '—' }} / {{ $student->pincode ?: '—' }}</dd>
                                <dt class="col-5">State / Country</dt><dd class="col-7">{{ optional($student->state)->name ?? '—' }} / {{ optional($student->country)->name ?? '—' }}</dd>
                                <dt class="col-5">Parent</dt><dd class="col-7">{{ $student->parent_name ?: '—' }} @if($student->parent_occupation) ({{ $student->parent_occupation }}) @endif</dd>
                                <dt class="col-5">Parent Contact</dt><dd class="col-7">{{ $student->parent_phone ?: '—' }} @if($student->parent_email) · {{ $student->parent_email }} @endif</dd>
                            </dl>
                            <h6 class="fw-semibold mt-3">Academic Details</h6>
                            @if($academic)
                                <dl class="row mb-0">
                                    <dt class="col-5">10th</dt>
                                    <dd class="col-7">{{ optional($academic->matriculationBoard)->name }} — {{ \App\Models\Admin\StudentAcademicDetail::formatMark($academic->matriculation_mark, $academic->matriculation_mark_type) }}</dd>
                                    <dt class="col-5">12th</dt>
                                    <dd class="col-7">{{ optional($academic->higherSecondaryBoard)->name }} ({{ $academic->higher_secondary_subject }}) — {{ \App\Models\Admin\StudentAcademicDetail::formatMark($academic->higher_secondary_mark, $academic->higher_secondary_mark_type) }}</dd>
                                </dl>
                            @else
                                <p class="text-muted mb-0">Not entered.</p>
                            @endif
                        </div></div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <button type="button" class="btn btn-light border" wire:click="goTo('documents')"><i class="ti ti-arrow-left me-1"></i> Back</button>
                    @if(!$readOnly && in_array($student->status, ['draft', 'pending_docs', 'rejected'], true))
                        <button type="button" class="btn btn-success px-4" wire:click="submit" wire:loading.attr="disabled">
                            <i class="ti ti-send me-1"></i> Submit for Approval
                        </button>
                    @elseif($student->submitted_at)
                        <span class="text-muted small">Submitted {{ $student->submitted_at->format('d M Y, h:i A') }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Remove document modal -->
    <div class="modal fade" id="documentDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
                    <h5>Remove Document</h5>
                    <p class="text-muted mb-4">Remove this uploaded document?</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger px-4" wire:click="deleteDocument">Yes, Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('livewire:load', function () {
    @if(session('toast'))
        window.dispatchEvent(new CustomEvent('show-toast', { detail: @json(session('toast')) }));
    @endif

    window.addEventListener('open-document-delete-modal', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('documentDeleteModal')).show();
    });
    window.addEventListener('close-document-delete-modal', () => {
        const instance = bootstrap.Modal.getInstance(document.getElementById('documentDeleteModal'));
        if (instance) {
            instance.hide();
        }
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
    });
});
</script>
