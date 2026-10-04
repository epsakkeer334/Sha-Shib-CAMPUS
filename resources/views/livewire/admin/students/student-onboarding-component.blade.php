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
        'basic' => ['Basic Details', 'ti ti-user', 'Institute, course & personal'],
        'address' => ['Address & Parent', 'ti ti-map-pin', 'Home address & guardian'],
        'academic' => ['Academic Details', 'ti ti-school', '10th & 12th results'],
        'documents' => ['KYC Documents', 'ti ti-file-certificate', 'Photo, certificates & marksheets'],
        'review' => ['Review & Submit', 'ti ti-checklist', 'Check & send for approval'],
    ];
    $doneCount = collect($done)->filter()->count();
    $progress = (int) round($doneCount / count($done) * 100);
    $days = $student ? $student->days_to_deadline : null;
    $deadlineTone = is_null($days) || $student->er_number ? 'muted' : ($days < 0 ? 'bad' : ($days <= config('camp.deadline_warning_days', 7) ? 'warn' : 'ok'));
    $requiredDocs = collect($documentTypes)->filter(fn ($t) => $t[1]);
    $uploadedRequired = $requiredDocs->keys()->filter(fn ($type) => $documents->has($type))->count();
    $tips = [
        'basic' => ['The institute and course decide which fees are charged.', 'Email and phone are used for the student portal login and notifications.', 'Onboarding must be completed within ' . config('camp.onboarding_days') . ' days of joining.'],
        'address' => ['Use the permanent home address.', 'Parent phone is used for emergencies and important updates.'],
        'academic' => ['Enter marks exactly as on the marksheet.', 'Percentage: 0–100 · CGPA: 0–10.'],
        'documents' => ['Files are stored privately — only authorised staff can open them.', 'A new upload replaces the earlier file of the same type.', 'Rejected documents must be uploaded again.'],
        'review' => ['Submitting opens Gate 1 (documents) and Gate 2 (fees).', 'Missing documents keep the student at "Pending Documents".'],
    ];
@endphp

<div class="content so-ui">
    {{-- Header --}}
    <div class="so-hero mb-3">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <span class="so-avatar">{!! $student ? e($student->initials) : '<i class="ti ti-user-plus"></i>' !!}</span>
            <div class="min-w-0">
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.students') }}">Students</a></li>
                        <li class="breadcrumb-item active">{{ $student ? 'Onboarding' : 'Add Student' }}</li>
                    </ol>
                </nav>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h2 class="mb-0 fw-bold">{{ $student ? $student->full_name : 'Add Student' }}</h2>
                    @if($student) {!! $student->status_html !!} @endif
                </div>
                @if($student)
                    <div class="so-meta">
                        @if($student->course)<span><span class="so-code">{{ $student->course->code }}</span> {{ $student->course->name }}</span>@endif
                        <span><i class="ti ti-building"></i> {{ optional($student->institute)->name }}</span>
                        <span><i class="ti ti-calendar"></i> Joining {{ $student->formatted_joining_date }}</span>
                        @if(!$student->er_number)
                            <span class="so-deadline so-deadline-{{ $deadlineTone }}"><i class="ti ti-clock"></i> Deadline {{ $student->formatted_onboarding_deadline }}
                                @if(!is_null($days)) · {{ $days < 0 ? abs($days) . ' days overdue' : ($days === 0 ? 'today' : $days . ' days left') }} @endif
                            </span>
                        @endif
                    </div>
                @else
                    <div class="text-muted small mt-1">Create the student step by step. Each step is saved on its own — you can finish later.</div>
                @endif
            </div>
        </div>
        <a href="{{ route('admin.students') }}" class="btn btn-light border"><i class="ti ti-arrow-left me-1"></i> Back to list</a>
    </div>

    @if($student)
        @include('livewire.admin.students.partials.student-nav', ['student' => $student, 'active' => 'onboarding'])
    @endif

    @if($student && $student->status === 'rejected')
        @php $rejectedGate = $student->approvals()->where('status', 'rejected')->latest('approved_at')->first(); @endphp
        <div class="so-alert so-alert-bad mb-3">
            <i class="ti ti-alert-triangle"></i>
            <div><strong>Returned for corrections.</strong> @if($rejectedGate) “{{ $rejectedGate->remarks }}” @endif
                Update the details or documents, then submit again from Review &amp; Submit.</div>
        </div>
    @endif
    @if($student && !$editable)
        <div class="so-alert so-alert-info mb-3"><i class="ti ti-lock"></i><div>Onboarding is closed for this student ({{ $student->status_label }}). Details are read-only.</div></div>
    @endif

    {{-- Stepper --}}
    <div class="so-steps mb-3" role="tablist">
        @foreach($tabs as $key => [$label, $icon, $sub])
            @php $locked = !$student && $key !== 'basic'; $isActive = $activeTab === $key; @endphp
            <button type="button" role="tab" aria-selected="{{ $isActive ? 'true' : 'false' }}" wire:click="goTo('{{ $key }}')"
                    class="so-step {{ $isActive ? 'is-active' : '' }} {{ $done[$key] ? 'is-done' : '' }} {{ $locked ? 'is-locked' : '' }}"
                    @if($locked) disabled title="Save basic details first" @endif>
                <span class="so-step-num">
                    @if($done[$key] && !$isActive)<i class="ti ti-check"></i>@elseif($locked)<i class="ti ti-lock"></i>@else{{ $loop->iteration }}@endif
                </span>
                <span class="so-step-text">
                    <span class="so-step-label">{{ $label }}</span>
                    <span class="so-step-sub">{{ $sub }}</span>
                </span>
            </button>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-9">
            <div class="so-panel">
                <div class="so-panel-head">
                    <span class="so-panel-icon"><i class="{{ $tabs[$activeTab][1] }}"></i></span>
                    <div>
                        <div class="so-panel-title">Step {{ array_search($activeTab, array_keys($tabs)) + 1 }} · {{ $tabs[$activeTab][0] }}</div>
                        <div class="small text-muted">{{ $tabs[$activeTab][2] }}</div>
                    </div>
                    @if($done[$activeTab])<span class="so-chip so-chip-ok ms-auto"><i class="ti ti-circle-check"></i> Completed</span>@endif
                </div>

                <div class="so-panel-body">
                <fieldset @if($readOnly) disabled @endif>

                {{-- ============ 1. BASIC DETAILS ============ --}}
                @if($activeTab === 'basic')
                    <div class="so-section">
                        <div class="so-section-title"><i class="ti ti-building-community"></i> Institute &amp; course</div>
                        <div class="row g-3">
                            @if($isSuperAdmin && !$student)
                                @include('livewire.admin.students.partials.select', ['name' => 'institute_id', 'label' => 'Institute', 'required' => true, 'live' => true, 'icon' => 'ti ti-building',
                                    'options' => $institutes->mapWithKeys(fn ($i) => [$i->id => "{$i->name} ({$i->code})"])])
                            @else
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Institute</label>
                                    <div class="fx-icon-field">
                                        <i class="ti ti-building fx-icon" aria-hidden="true"></i>
                                        <input type="text" class="form-control bg-light" readonly
                                               value="{{ optional($institutes->firstWhere('id', $institute_id) ?? optional($student)->institute)->name }}">
                                    </div>
                                </div>
                            @endif
                            @include('livewire.admin.students.partials.select', ['name' => 'course_id', 'label' => 'Course', 'required' => true, 'icon' => 'ti ti-books',
                                'options' => $courses->mapWithKeys(fn ($c) => [$c->id => "{$c->name} ({$c->code})"]),
                                'placeholder' => $institute_id ? ($courses->isEmpty() ? 'No active courses at this institute' : 'Select course') : 'Select institute first',
                                'help' => $institute_id && $courses->isEmpty() ? 'Assign courses under Institute Management → Institute Courses.' : null])
                            @include('livewire.admin.students.partials.input', ['name' => 'joining_date', 'label' => 'Joining date', 'type' => 'date', 'required' => true, 'col' => 4, 'icon' => 'ti ti-calendar-event',
                                'help' => 'Onboarding deadline: ' . config('camp.onboarding_days') . ' days after joining.'])
                        </div>
                    </div>

                    <div class="so-section">
                        <div class="so-section-title"><i class="ti ti-id"></i> Personal details</div>
                        <div class="row g-3">
                            @include('livewire.admin.students.partials.input', ['name' => 'first_name', 'label' => 'First name', 'required' => true, 'col' => 4, 'icon' => 'ti ti-user'])
                            @include('livewire.admin.students.partials.input', ['name' => 'last_name', 'label' => 'Last name', 'required' => true, 'col' => 4, 'icon' => 'ti ti-user'])
                            @include('livewire.admin.students.partials.input', ['name' => 'dob', 'label' => 'Date of birth', 'type' => 'date', 'required' => true, 'col' => 4, 'icon' => 'ti ti-cake'])
                            <div class="col-md-4">
                                <label class="form-label fw-medium small d-block">Gender <span class="text-danger">*</span></label>
                                <div class="so-segment @error('gender') is-invalid @enderror" role="radiogroup" aria-label="Gender">
                                    @foreach(config('camp.genders') as $value => $genderLabel)
                                        <input type="radio" class="btn-check" name="gender" id="gender_{{ $value }}" value="{{ $value }}" wire:model.defer="gender">
                                        <label class="so-segment-btn" for="gender_{{ $value }}">{{ $genderLabel }}</label>
                                    @endforeach
                                </div>
                                @error('gender') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            @include('livewire.admin.students.partials.select', ['name' => 'qualification_id', 'label' => 'Qualification', 'required' => true, 'col' => 4, 'icon' => 'ti ti-certificate', 'options' => $qualifications])
                            @include('livewire.admin.students.partials.select', ['name' => 'religion_id', 'label' => 'Religion', 'required' => true, 'col' => 4, 'live' => true, 'icon' => 'ti ti-heart-handshake', 'options' => $religions])
                            @include('livewire.admin.students.partials.select', ['name' => 'category_id', 'label' => 'Category', 'required' => true, 'col' => 4, 'icon' => 'ti ti-category', 'options' => $categories,
                                'placeholder' => $religion_id ? 'Select category' : 'Select religion first'])
                        </div>
                    </div>

                    <div class="so-section">
                        <div class="so-section-title"><i class="ti ti-address-book"></i> Contact</div>
                        <div class="row g-3">
                            @include('livewire.admin.students.partials.input', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'col' => 4, 'icon' => 'ti ti-mail', 'placeholder' => 'student@example.com'])
                            @include('livewire.admin.students.partials.input', ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'col' => 4, 'icon' => 'ti ti-phone', 'placeholder' => '+919876543210'])
                            @include('livewire.admin.students.partials.input', ['name' => 'emergency_contact', 'label' => 'Emergency contact', 'type' => 'tel', 'required' => true, 'col' => 4, 'icon' => 'ti ti-urgent', 'placeholder' => '+919876543210'])
                        </div>
                    </div>

                    @php $loginUser = optional($student)->user; @endphp
                    <div class="so-section">
                        <div class="so-section-title"><i class="ti ti-key"></i> Student portal login
                            @if($loginUser)
                                <span class="so-chip {{ $loginUser->status ? 'so-chip-ok' : 'so-chip-bad' }} ms-auto">{{ $loginUser->status ? 'Login active' : 'Login disabled' }}</span>
                            @endif
                        </div>

                        @if($loginUser)
                            <div class="so-login-card mb-3">
                                <span class="so-login-icon"><i class="ti ti-user-shield"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="small text-muted">Username (email)</div>
                                    <div class="fw-semibold text-truncate">{{ $loginUser->email }}</div>
                                </div>
                                <div class="text-end small">
                                    <div class="text-muted">Last sign-in</div>
                                    <div class="fw-medium">{{ $loginUser->last_login_at ? $loginUser->last_login_at->diffForHumans() : 'Never' }}</div>
                                </div>
                            </div>
                            <div class="small text-muted mb-2">The login follows the email and phone above. Fill the fields below only to set a new password.</div>
                        @else
                            <label class="so-toggle mb-3">
                                <input class="form-check-input" type="checkbox" wire:model="create_login" @if($readOnly) disabled @endif>
                                <span>
                                    <span class="fw-semibold d-block">{{ $student ? 'Create a portal login for this student' : 'Create a portal login' }}</span>
                                    <span class="small text-muted">The student signs in at <span class="so-mono">{{ route('portal.login') }}</span> with the email above and this password, to track the application, upload documents and pay fees.</span>
                                </span>
                            </label>
                        @endif

                        @if($loginUser || $create_login)
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label fw-medium small" for="f_login_password">{{ $loginUser ? 'New password' : 'Password' }} @unless($loginUser)<span class="text-danger">*</span>@endunless</label>
                                    <div class="fx-icon-field so-password">
                                        <i class="ti ti-lock fx-icon" aria-hidden="true"></i>
                                        <input id="f_login_password" type="password" class="form-control @error('login_password') is-invalid @enderror" wire:model.defer="login_password"
                                               autocomplete="new-password" placeholder="{{ $loginUser ? 'Leave blank to keep the current password' : 'At least 8 characters' }}">
                                        <button type="button" class="so-eye" onclick="soTogglePassword(this)" aria-label="Show password" title="Show / hide"><i class="ti ti-eye"></i></button>
                                    </div>
                                    @error('login_password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-medium small" for="f_login_password_confirmation">Confirm password @unless($loginUser)<span class="text-danger">*</span>@endunless</label>
                                    <div class="fx-icon-field so-password">
                                        <i class="ti ti-lock-check fx-icon" aria-hidden="true"></i>
                                        <input id="f_login_password_confirmation" type="password" class="form-control" wire:model.defer="login_password_confirmation" autocomplete="new-password">
                                        <button type="button" class="so-eye" onclick="soTogglePassword(this)" aria-label="Show password" title="Show / hide"><i class="ti ti-eye"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-secondary w-100" wire:click="generatePassword" @if($readOnly) disabled @endif>
                                        <i class="ti ti-wand me-1"></i> Generate
                                    </button>
                                </div>
                                @if($login_password && $login_password === $login_password_confirmation)
                                    <div class="col-12">
                                        <div class="so-alert so-alert-info py-2">
                                            <i class="ti ti-info-circle"></i>
                                            <div>Share this password with the student securely: <span class="so-mono fw-semibold">{{ $login_password }}</span> — it is shown only until you save.</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                {{-- ============ 2. ADDRESS & PARENT ============ --}}
                @if($activeTab === 'address')
                    <div class="so-section">
                        <div class="so-section-title"><i class="ti ti-home"></i> Address</div>
                        <div class="row g-3">
                            @include('livewire.admin.students.partials.input', ['name' => 'address', 'label' => 'Street address', 'type' => 'textarea', 'required' => true, 'col' => 12, 'icon' => 'ti ti-map-pin', 'placeholder' => 'House / street / area'])
                            @include('livewire.admin.students.partials.select', ['name' => 'country_id', 'label' => 'Country', 'required' => true, 'col' => 3, 'live' => true, 'icon' => 'ti ti-world', 'options' => $countries])
                            @include('livewire.admin.students.partials.select', ['name' => 'state_id', 'label' => 'State', 'required' => true, 'col' => 3, 'icon' => 'ti ti-map', 'options' => $states,
                                'placeholder' => $country_id ? 'Select state' : 'Select country first'])
                            @include('livewire.admin.students.partials.input', ['name' => 'city', 'label' => 'City', 'required' => true, 'col' => 3, 'icon' => 'ti ti-building-skyscraper'])
                            @include('livewire.admin.students.partials.input', ['name' => 'pincode', 'label' => 'Pincode', 'required' => true, 'col' => 3, 'icon' => 'ti ti-mailbox', 'maxlength' => 10])
                        </div>
                    </div>

                    <div class="so-section">
                        <div class="so-section-title"><i class="ti ti-users"></i> Parent / guardian</div>
                        <div class="row g-3">
                            @include('livewire.admin.students.partials.input', ['name' => 'parent_name', 'label' => 'Parent name', 'required' => true, 'icon' => 'ti ti-user'])
                            @include('livewire.admin.students.partials.input', ['name' => 'parent_occupation', 'label' => 'Occupation', 'icon' => 'ti ti-briefcase'])
                            @include('livewire.admin.students.partials.input', ['name' => 'parent_phone', 'label' => 'Parent phone', 'type' => 'tel', 'required' => true, 'icon' => 'ti ti-phone', 'placeholder' => '+919876543210'])
                            @include('livewire.admin.students.partials.input', ['name' => 'parent_email', 'label' => 'Parent email', 'type' => 'email', 'icon' => 'ti ti-mail'])
                        </div>
                    </div>
                @endif

                {{-- ============ 3. ACADEMIC DETAILS ============ --}}
                @if($activeTab === 'academic')
                    @foreach([
                        ['matriculation', 'Matriculation (10th)', 'ti ti-certificate', $matriculationBoards, $matriculation_mark_type],
                        ['higher_secondary', 'Higher secondary (12th)', 'ti ti-certificate-2', $higherSecondaryBoards, $higher_secondary_mark_type],
                    ] as [$prefix, $title, $icon, $boards, $markType])
                        <div class="so-section">
                            <div class="so-section-title"><i class="{{ $icon }}"></i> {{ $title }}</div>
                            <div class="row g-3">
                                @include('livewire.admin.students.partials.select', ['name' => "{$prefix}_board_id", 'label' => 'Board', 'required' => true, 'col' => $prefix === 'matriculation' ? 6 : 4, 'icon' => 'ti ti-building-bank', 'options' => $boards])
                                @if($prefix === 'higher_secondary')
                                    @include('livewire.admin.students.partials.select', ['name' => 'higher_secondary_subject', 'label' => 'Subject stream', 'required' => true, 'col' => 2, 'options' => config('camp.higher_secondary_subjects')])
                                @endif
                                <div class="col-md-3">
                                    <label class="form-label fw-medium small d-block">Mark in <span class="text-danger">*</span></label>
                                    <div class="so-segment @error("{$prefix}_mark_type") is-invalid @enderror" role="radiogroup" aria-label="{{ $title }} mark type">
                                        @foreach(config('camp.mark_types') as $value => $typeLabel)
                                            <input type="radio" class="btn-check" name="{{ $prefix }}_mark_type" id="{{ $prefix }}_type_{{ $value }}" value="{{ $value }}" wire:model="{{ $prefix }}_mark_type">
                                            <label class="so-segment-btn" for="{{ $prefix }}_type_{{ $value }}">{{ $value === 'cgpa' ? 'CGPA' : '%' }}</label>
                                        @endforeach
                                    </div>
                                    @error("{$prefix}_mark_type") <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                @include('livewire.admin.students.partials.input', ['name' => "{$prefix}_mark", 'label' => 'Mark', 'type' => 'number', 'step' => '0.01', 'required' => true, 'col' => 3,
                                    'icon' => $markType === 'cgpa' ? 'ti ti-star' : 'ti ti-percentage', 'placeholder' => $markType === 'cgpa' ? '0 – 10' : '0 – 100'])
                            </div>
                        </div>
                    @endforeach
                @endif

                {{-- ============ 4. KYC DOCUMENTS ============ --}}
                @if($activeTab === 'documents')
                    <div class="so-docs-summary mb-3">
                        <div>
                            <div class="fw-semibold">{{ $uploadedRequired }} of {{ $requiredDocs->count() }} required documents uploaded</div>
                            <div class="small text-muted">Files are stored privately. The Institute Admin verifies them after submission.</div>
                        </div>
                        <div class="so-bar"><span style="width: {{ $requiredDocs->count() ? round($uploadedRequired / $requiredDocs->count() * 100) : 0 }}%;"></span></div>
                    </div>
                    <div class="row g-3">
                        @foreach($documentTypes as $type => [$label, $required, $mimes, $maxKb])
                            @php
                                $files = $documents->get($type, collect());
                                $latest = $files->first();
                                $state = !$latest ? 'missing' : $latest->verification_status;
                            @endphp
                            <div class="col-lg-6" wire:key="doc-{{ $type }}">
                                <div class="so-doc so-doc-{{ $state }}">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="so-doc-icon"><i class="ti ti-{{ $type === 'kyc_photo' ? 'photo' : 'file-text' }}"></i></span>
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <div class="fw-semibold">{{ $label }} @if($required)<span class="text-danger">*</span>@else<span class="so-chip so-chip-muted ms-1">Optional</span>@endif</div>
                                                @php
                                                    [$chipText, $chipTone] = match ($state) {
                                                        'verified' => ['Verified', 'ok'], 'rejected' => ['Rejected', 'bad'],
                                                        'pending' => ['Uploaded', 'info'], default => [$required ? 'Required' : 'Not uploaded', $required ? 'warn' : 'muted'],
                                                    };
                                                @endphp
                                                <span class="so-chip so-chip-{{ $chipTone }}">{{ $chipText }}</span>
                                            </div>
                                            <div class="small text-muted">{{ strtoupper(str_replace(',', ', ', $mimes)) }} · max {{ $maxKb / 1024 }} MB</div>
                                        </div>
                                    </div>

                                    @php
                                        $inputId = 'up_' . $type;
                                        $accept = collect(explode(',', $mimes))->map(fn ($m) => '.' . $m)->implode(',');
                                    @endphp
                                    @unless($readOnly)
                                        {{-- One hidden picker per type: choosing a file uploads it at once --}}
                                        <input type="file" id="{{ $inputId }}" class="d-none" wire:key="input-{{ $type }}" wire:model="upload_{{ $type }}" accept="{{ $accept }}" aria-label="Choose {{ $label }}">
                                    @endunless

                                    @foreach($files as $doc)
                                        <div class="so-file" wire:key="file-{{ $doc->id }}">
                                            @if($doc->is_image)
                                                <img src="{{ route('admin.students.documents.show', $doc->id) }}" alt="" class="so-file-thumb" loading="lazy">
                                            @else
                                                <span class="so-file-thumb so-file-pdf"><i class="ti ti-file-type-pdf"></i></span>
                                            @endif
                                            <div class="min-w-0 flex-grow-1">
                                                <a href="{{ route('admin.students.documents.show', $doc->id) }}" target="_blank" class="so-file-name text-truncate d-block" title="{{ $doc->original_name }}">{{ $doc->original_name }}</a>
                                                <div class="small text-muted">{{ $doc->size_label }} · {{ $doc->uploaded_at->format('d M Y, h:i A') }}</div>
                                                @if($doc->verification_status === 'rejected' && $doc->remarks)
                                                    <div class="small text-danger"><i class="ti ti-alert-circle"></i> {{ $doc->remarks }} — replace with a new copy.</div>
                                                @endif
                                            </div>
                                            <div class="so-file-actions">
                                                <a href="{{ route('admin.students.documents.show', $doc->id) }}" target="_blank" class="btn btn-sm btn-light border" title="View" aria-label="View {{ $doc->original_name }}"><i class="ti ti-eye"></i></a>
                                                @unless($readOnly)
                                                    @if($type !== 'other')
                                                        <label for="{{ $inputId }}" class="btn btn-sm {{ $doc->verification_status === 'rejected' ? 'btn-warning' : 'btn-outline-primary' }} mb-0" title="Replace with a new file" role="button">
                                                            <i class="ti ti-replace"></i> Replace
                                                        </label>
                                                    @endif
                                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmDeleteDocument({{ $doc->id }})" title="Delete" aria-label="Delete {{ $doc->original_name }}">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                @endunless
                                            </div>
                                        </div>
                                    @endforeach

                                    @unless($readOnly)
                                        @if($files->isEmpty() || $type === 'other')
                                            <label for="{{ $inputId }}" class="so-drop mb-0" role="button" wire:key="drop-{{ $type }}">
                                                <i class="ti ti-cloud-upload"></i>
                                                <span><strong>{{ $files->isEmpty() ? 'Choose file' : 'Add another file' }}</strong> — uploads automatically</span>
                                            </label>
                                        @endif
                                        <div class="so-uploading" wire:key="uploading-{{ $type }}" wire:loading.flex wire:target="upload_{{ $type }}">
                                            <span class="spinner-border spinner-border-sm"></span> Uploading {{ $label }}…
                                        </div>
                                        @error('upload_' . $type) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    @endunless
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                </fieldset>

                {{-- ============ 5. REVIEW & SUBMIT ============ --}}
                @if($activeTab === 'review' && $student)
                    @php $academic = $student->academicDetail; @endphp

                    @if($checklist)
                        <div class="so-alert so-alert-warn mb-3">
                            <i class="ti ti-alert-triangle"></i>
                            <div>
                                <strong>Still missing</strong>
                                <ul class="mb-0 mt-1 ps-3">
                                    @foreach($checklist as $tab => $items)
                                        @foreach($items as $item)
                                            <li>{{ $item }} <a href="javascript:void(0);" wire:click="goTo('{{ $tab }}')" class="fw-semibold">Fix</a></li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @else
                        <div class="so-alert so-alert-ok mb-3"><i class="ti ti-circle-check"></i><div>All details and required documents are in place.</div></div>
                    @endif

                    <div class="row g-3">
                        @foreach([
                            ['basic', 'Basic details', 'ti ti-user', [
                                'Institute' => optional($student->institute)->name,
                                'Course' => optional($student->course)->name . ' (' . optional($student->course)->code . ')',
                                'Name' => $student->full_name,
                                'Date of birth' => optional($student->dob)->format('d M Y'),
                                'Gender' => config('camp.genders.' . $student->gender),
                                'Qualification' => optional($student->qualification)->name,
                                'Religion / Category' => optional($student->religion)->name . ' / ' . optional($student->category)->name,
                                'Email' => $student->email,
                                'Phone / Emergency' => $student->phone . ' / ' . $student->emergency_contact,
                                'Joining / Deadline' => $student->formatted_joining_date . ' / ' . $student->formatted_onboarding_deadline,
                            ]],
                            ['address', 'Address & parent', 'ti ti-map-pin', [
                                'Address' => $student->address ?: '—',
                                'City / Pincode' => ($student->city ?: '—') . ' / ' . ($student->pincode ?: '—'),
                                'State / Country' => (optional($student->state)->name ?? '—') . ' / ' . (optional($student->country)->name ?? '—'),
                                'Parent' => ($student->parent_name ?: '—') . ($student->parent_occupation ? " ({$student->parent_occupation})" : ''),
                                'Parent contact' => ($student->parent_phone ?: '—') . ($student->parent_email ? " · {$student->parent_email}" : ''),
                            ]],
                            ['academic', 'Academic details', 'ti ti-school', $academic ? [
                                '10th' => optional($academic->matriculationBoard)->name . ' — ' . \App\Models\Admin\StudentAcademicDetail::formatMark($academic->matriculation_mark, $academic->matriculation_mark_type),
                                '12th' => optional($academic->higherSecondaryBoard)->name . " ({$academic->higher_secondary_subject}) — " . \App\Models\Admin\StudentAcademicDetail::formatMark($academic->higher_secondary_mark, $academic->higher_secondary_mark_type),
                            ] : ['Status' => 'Not entered']],
                            ['documents', 'KYC documents', 'ti ti-file-certificate', collect($documentTypes)->mapWithKeys(fn ($t, $type) => [$t[0] => $documents->has($type) ? ucfirst($documents->get($type)->first()->verification_status) : ($t[1] ? 'Missing' : '—')])->all()],
                        ] as [$tabKey, $title, $icon, $rows])
                            <div class="col-lg-6">
                                <div class="so-review">
                                    <div class="so-review-head">
                                        <span><i class="{{ $icon }}"></i> {{ $title }}</span>
                                        @unless($readOnly)<a href="javascript:void(0);" wire:click="goTo('{{ $tabKey }}')" class="small fw-semibold"><i class="ti ti-edit"></i> Edit</a>@endunless
                                    </div>
                                    <dl class="so-facts">
                                        @foreach($rows as $k => $v)
                                            <dt>{{ $k }}</dt>
                                            <dd class="{{ in_array($v, ['Missing', 'Rejected', 'Not entered'], true) ? 'text-danger fw-semibold' : (in_array($v, ['Verified'], true) ? 'text-success' : '') }}">{{ $v ?: '—' }}</dd>
                                        @endforeach
                                    </dl>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                </div>

                {{-- Footer actions --}}
                <div class="so-panel-foot">
                    @php
                        $keys = array_keys($tabs);
                        $index = array_search($activeTab, $keys);
                        $prev = $index > 0 ? $keys[$index - 1] : null;
                    @endphp
                    <div>
                        @if($prev)
                            <button type="button" class="btn btn-light border" wire:click="goTo('{{ $prev }}')"><i class="ti ti-arrow-left me-1"></i> Back</button>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if($activeTab === 'basic' && !$readOnly)
                            <button type="button" class="btn btn-primary px-4" wire:click="saveBasic" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveBasic"><i class="ti ti-device-floppy me-1"></i> {{ $student ? 'Save & Continue' : 'Create Student & Continue' }}</span>
                                <span wire:loading wire:target="saveBasic"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                            </button>
                        @elseif($activeTab === 'address' && !$readOnly)
                            <button type="button" class="btn btn-primary px-4" wire:click="saveAddress" wire:loading.attr="disabled"><i class="ti ti-device-floppy me-1"></i> Save &amp; Continue</button>
                        @elseif($activeTab === 'academic' && !$readOnly)
                            <button type="button" class="btn btn-primary px-4" wire:click="saveAcademic" wire:loading.attr="disabled"><i class="ti ti-device-floppy me-1"></i> Save &amp; Continue</button>
                        @elseif($activeTab === 'documents')
                            <button type="button" class="btn btn-primary px-4" wire:click="goTo('review')">Continue to Review <i class="ti ti-arrow-right ms-1"></i></button>
                        @elseif($activeTab === 'review' && $student)
                            @if(!$readOnly && in_array($student->status, ['draft', 'pending_docs', 'rejected'], true))
                                <button type="button" class="btn btn-success px-4" wire:click="submit" wire:loading.attr="disabled">
                                    <i class="ti ti-send me-1"></i> Submit for Approval
                                </button>
                            @elseif($student->submitted_at)
                                <span class="so-chip so-chip-ok"><i class="ti ti-circle-check"></i> Submitted {{ $student->submitted_at->format('d M Y, h:i A') }}</span>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Side: progress, checklist, tips --}}
        <div class="col-xl-3">
            <div class="so-side">
                <div class="so-panel p-3 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="so-ring" style="--p: {{ $progress }};"><span>{{ $progress }}%</span></div>
                        <div>
                            <div class="fw-semibold">Onboarding progress</div>
                            <div class="small text-muted">{{ $doneCount }} of {{ count($done) }} steps complete</div>
                        </div>
                    </div>
                    <ul class="so-checklist">
                        @foreach($tabs as $key => [$label, $icon])
                            <li class="{{ $done[$key] ? 'is-done' : '' }} {{ $activeTab === $key ? 'is-current' : '' }}">
                                <i class="ti ti-{{ $done[$key] ? 'circle-check-filled' : 'circle' }}"></i> {{ $label }}
                                @if($key === 'documents' && $student)<span class="ms-auto small text-muted">{{ $uploadedRequired }}/{{ $requiredDocs->count() }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="so-panel p-3 so-tips">
                    <div class="fw-semibold mb-2"><i class="ti ti-bulb"></i> Tips for this step</div>
                    <ul>
                        @foreach($tips[$activeTab] as $tip)<li>{{ $tip }}</li>@endforeach
                    </ul>
                </div>
            </div>
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

    <style>
        .so-ui { --so-border: #E5E7EB; --so-soft: #F1F2F4; --so-ink: #111827; --so-muted: #6B7280; --so-accent: #F26522; }
        .so-ui .min-w-0 { min-width: 0; }
        /* hero */
        .so-ui .so-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--so-border);
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .so-ui .so-hero h2 { font-size: 22px; color: var(--so-ink); }
        .so-ui .so-avatar { width: 56px; height: 56px; border-radius: 16px; background: #FEF0E7; color: var(--so-accent); font-weight: 700; font-size: 20px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 12px rgba(242, 101, 34, .15); }
        .so-ui .so-avatar i { font-size: 26px; }
        .so-ui .so-meta { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 6px; font-size: 13px; color: #4B5563; }
        .so-ui .so-meta > span { display: inline-flex; align-items: center; gap: 4px; }
        .so-ui .so-code { padding: 0 6px; border-radius: 5px; background: #EEF2FF; color: #4338CA; font-weight: 600; font-size: 11.5px; }
        .so-ui .so-deadline-ok { color: #15803D; } .so-ui .so-deadline-warn { color: #B45309; font-weight: 600; } .so-ui .so-deadline-bad { color: #DC2626; font-weight: 600; }
        /* alerts */
        .so-ui .so-alert { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 12px; font-size: 13.5px; }
        .so-ui .so-alert > i { font-size: 18px; margin-top: 1px; }
        .so-ui .so-alert-bad { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; }
        .so-ui .so-alert-info { background: #F0F9FF; border: 1px solid #BAE6FD; color: #075985; }
        .so-ui .so-alert-warn { background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; }
        .so-ui .so-alert-ok { background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; }
        /* stepper */
        .so-ui .so-steps { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; }
        .so-ui .so-step { position: relative; display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #fff; border: 1px solid var(--so-border); border-radius: 12px; text-align: left; transition: border-color .15s, box-shadow .15s, transform .15s; min-width: 0; }
        .so-ui .so-step:not(:disabled):hover { border-color: #FDBA8C; transform: translateY(-1px); }
        .so-ui .so-step.is-active { border-color: var(--so-accent); box-shadow: 0 0 0 3px rgba(242, 101, 34, .12); background: linear-gradient(180deg, #FFF7F2 0%, #fff 80%); }
        .so-ui .so-step.is-locked { opacity: .55; cursor: not-allowed; }
        .so-ui .so-step-num { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 700; font-size: 14px; background: #F3F4F6; color: #6B7280; border: 2px solid #E5E7EB; }
        .so-ui .so-step.is-done .so-step-num { background: #22C55E; border-color: #22C55E; color: #fff; }
        .so-ui .so-step.is-active .so-step-num { background: var(--so-accent); border-color: var(--so-accent); color: #fff; }
        .so-ui .so-step-text { display: flex; flex-direction: column; min-width: 0; }
        .so-ui .so-step-label { font-weight: 600; font-size: 13.5px; color: var(--so-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .so-ui .so-step-sub { font-size: 11.5px; color: var(--so-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        /* panel */
        .so-ui .so-panel { background: #fff; border: 1px solid var(--so-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        .so-ui .so-panel-head { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--so-soft); }
        .so-ui .so-panel-icon { width: 40px; height: 40px; border-radius: 11px; background: #FEF0E7; color: var(--so-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 20px; }
        .so-ui .so-panel-title { font-weight: 700; font-size: 16px; color: var(--so-ink); }
        .so-ui .so-panel-body { padding: 20px; }
        .so-ui .so-panel-foot { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 20px; border-top: 1px solid var(--so-soft); background: #FCFCFD; border-radius: 0 0 14px 14px; position: sticky; bottom: 0; z-index: 3; }
        .so-ui .so-section { padding: 18px; border: 1px solid var(--so-soft); border-radius: 12px; background: #FCFCFD; }
        .so-ui .so-section + .so-section { margin-top: 16px; }
        .so-ui .so-section-title { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; color: #374151; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px dashed var(--so-border); }
        .so-ui .so-section-title i { color: var(--so-accent); font-size: 18px; }
        /* fields */
        .so-ui .form-label { color: #374151; margin-bottom: 5px; }
        .so-ui .form-control, .so-ui .form-select { border-radius: 9px; border-color: #D1D5DB; min-height: 40px; background-color: #fff; transition: border-color .15s, box-shadow .15s; }
        .so-ui .form-control:focus, .so-ui .form-select:focus { border-color: var(--so-accent); box-shadow: 0 0 0 3px rgba(242, 101, 34, .14); }
        .so-ui fieldset:disabled .form-control, .so-ui fieldset:disabled .form-select { background-color: #F9FAFB; }
        .so-ui .fx-icon-field { position: relative; }
        .so-ui .fx-icon-field .fx-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9CA3AF; font-size: 16px; pointer-events: none; z-index: 2; }
        .so-ui .fx-icon-field.fx-textarea .fx-icon { top: 13px; transform: none; }
        .so-ui .fx-icon-field .form-control, .so-ui .fx-icon-field .form-select { padding-left: 36px; }
        .so-ui .fx-icon-field:focus-within .fx-icon { color: var(--so-accent); }
        .so-ui .fx-help { display: block; margin-top: 4px; font-size: 11.5px; }
        .so-ui .so-segment { display: flex; gap: 4px; padding: 3px; background: #F3F4F6; border-radius: 10px; border: 1px solid transparent; }
        .so-ui .so-segment.is-invalid { border-color: #DC3545; }
        .so-ui .so-segment-btn { flex: 1; text-align: center; padding: 6px 8px; border-radius: 8px; font-size: 13px; font-weight: 500; color: #4B5563; cursor: pointer; margin: 0; transition: background .15s; white-space: nowrap; }
        .so-ui .btn-check:checked + .so-segment-btn { background: #fff; color: var(--so-accent); font-weight: 600; box-shadow: 0 1px 3px rgba(16, 24, 40, .12); }
        .so-ui .btn-check:focus-visible + .so-segment-btn { outline: 2px solid var(--so-accent); }
        .so-ui .so-login-card { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 10px; background: #fff; border: 1px solid var(--so-border); }
        .so-ui .so-login-icon { width: 38px; height: 38px; border-radius: 10px; background: #DCFCE7; color: #15803D; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; flex-shrink: 0; }
        .so-ui .so-toggle { display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border-radius: 10px; background: #fff; border: 1px solid var(--so-border); cursor: pointer; width: 100%; }
        .so-ui .so-toggle .form-check-input { width: 1.2em; height: 1.2em; margin-top: 2px; flex-shrink: 0; }
        .so-ui .so-toggle .form-check-input:checked { background-color: var(--so-accent); border-color: var(--so-accent); }
        .so-ui .so-mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .so-ui .so-password .form-control { padding-right: 40px; }
        .so-ui .so-eye { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: #9CA3AF; padding: 4px 6px; z-index: 2; }
        .so-ui .so-eye:hover { color: var(--so-accent); }
        /* chips */
        .so-ui .so-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
        .so-ui .so-chip-ok { background: #DCFCE7; color: #15803D; } .so-ui .so-chip-warn { background: #FEF3C7; color: #B45309; }
        .so-ui .so-chip-bad { background: #FEE2E2; color: #DC2626; } .so-ui .so-chip-info { background: #E0F2FE; color: #0369A1; }
        .so-ui .so-chip-muted { background: #F3F4F6; color: var(--so-muted); }
        /* documents */
        .so-ui .so-docs-summary { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 14px 16px; border-radius: 12px; background: #FCFCFD; border: 1px solid var(--so-soft); }
        .so-ui .so-bar { width: 220px; max-width: 100%; height: 8px; border-radius: 999px; background: #F3F4F6; overflow: hidden; }
        .so-ui .so-bar span { display: block; height: 100%; background: linear-gradient(90deg, #22C55E, #16A34A); border-radius: 999px; }
        .so-ui .so-doc { height: 100%; display: flex; flex-direction: column; gap: 12px; padding: 14px; border: 1px solid var(--so-border); border-radius: 12px; background: #fff; }
        .so-ui .so-doc-verified { border-color: #86EFAC; } .so-ui .so-doc-rejected { border-color: #FCA5A5; background: #FFFBFB; }
        .so-ui .so-doc-missing { border-style: dashed; }
        .so-ui .so-doc-icon { width: 40px; height: 40px; border-radius: 10px; background: #EEF2FF; color: #4338CA; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .so-ui .so-doc-verified .so-doc-icon { background: #DCFCE7; color: #15803D; } .so-ui .so-doc-rejected .so-doc-icon { background: #FEE2E2; color: #DC2626; }
        .so-ui .so-file { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 10px; background: #F9FAFB; border: 1px solid var(--so-soft); }
        .so-ui .so-file-thumb { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; flex-shrink: 0; border: 1px solid var(--so-border); background: #fff; }
        .so-ui .so-file-pdf { display: inline-flex; align-items: center; justify-content: center; color: #DC2626; font-size: 22px; }
        .so-ui .so-file-name { font-weight: 500; font-size: 13px; }
        .so-ui .so-file-actions { display: flex; gap: 4px; flex-shrink: 0; }
        .so-ui .so-file-actions label { cursor: pointer; }
        .so-ui .so-drop { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: auto; padding: 14px; border: 2px dashed #D1D5DB; border-radius: 10px; background: #FAFAFB; color: #4B5563; font-size: 13px; cursor: pointer; transition: border-color .15s, background .15s; }
        .so-ui .so-drop i { font-size: 22px; color: var(--so-accent); }
        .so-ui .so-drop:hover { border-color: var(--so-accent); background: #FFF7F2; }
        .so-ui .so-uploading { display: none; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 8px; background: #FFF7F2; color: #B45309; font-size: 12.5px; }
        /* review */
        .so-ui .so-review { height: 100%; border: 1px solid var(--so-border); border-radius: 12px; overflow: hidden; }
        .so-ui .so-review-head { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #F9FAFB; border-bottom: 1px solid var(--so-soft); font-weight: 600; font-size: 14px; }
        .so-ui .so-review-head i { color: var(--so-accent); }
        .so-ui .so-facts { display: grid; grid-template-columns: minmax(110px, 40%) 1fr; margin: 0; padding: 4px 14px 8px; font-size: 13px; }
        .so-ui .so-facts dt, .so-ui .so-facts dd { padding: 6px 0; border-bottom: 1px dashed var(--so-soft); }
        .so-ui .so-facts dt { color: var(--so-muted); font-weight: 400; padding-right: 10px; }
        .so-ui .so-facts dd { margin: 0; color: var(--so-ink); word-break: break-word; }
        /* side */
        .so-ui .so-side { position: sticky; top: 80px; }
        .so-ui .so-ring { --p: 0; width: 64px; height: 64px; border-radius: 50%; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;
            background: radial-gradient(closest-side, #fff 78%, transparent 80% 100%), conic-gradient(var(--so-accent) calc(var(--p) * 1%), #F3F4F6 0); }
        .so-ui .so-ring span { font-weight: 700; font-size: 14px; color: var(--so-ink); }
        .so-ui .so-checklist { list-style: none; margin: 14px 0 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
        .so-ui .so-checklist li { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; font-size: 13px; color: #6B7280; }
        .so-ui .so-checklist li i { font-size: 17px; color: #D1D5DB; }
        .so-ui .so-checklist li.is-done { color: #374151; } .so-ui .so-checklist li.is-done i { color: #22C55E; }
        .so-ui .so-checklist li.is-current { background: #FFF7F2; color: var(--so-ink); font-weight: 600; }
        .so-ui .so-tips ul { margin: 0; padding-left: 18px; font-size: 12.5px; color: #4B5563; display: flex; flex-direction: column; gap: 6px; }
        .so-ui .so-tips .ti-bulb { color: #F59E0B; }
        @media (max-width: 1199.98px) { .so-ui .so-steps { grid-template-columns: repeat(5, minmax(150px, 1fr)); overflow-x: auto; padding-bottom: 4px; } .so-ui .so-side { position: static; } }
        @media (max-width: 575.98px) { .so-ui .so-panel-body { padding: 14px; } .so-ui .so-section { padding: 14px; } }
    </style>
</div>

<script>
function soTogglePassword(button) {
    const input = button.parentElement.querySelector('input');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.querySelector('i').className = show ? 'ti ti-eye-off' : 'ti ti-eye';
}

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
