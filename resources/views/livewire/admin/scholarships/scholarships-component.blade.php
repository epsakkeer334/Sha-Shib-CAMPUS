<div class="content sc-ui">
    <!-- Hero -->
    <div class="sc-hero mb-3">
        <div class="sc-hero-main">
            <span class="sc-hero-icon"><i class="ti ti-award"></i></span>
            <div>
                <h2 class="mb-1">Scholarships</h2>
                <nav><ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Scholarships</li><li class="breadcrumb-item active">Schemes</li>
                </ol></nav>
                <p class="mb-0 sc-hero-sub">Percentage or fixed discounts on semester fees — calculated on each student's dues automatically.</p>
            </div>
        </div>
        <div class="sc-hero-stats">
            <div><b>{{ $stats['active'] }}</b><span>active schemes</span></div>
            <div><b>{{ number_format($stats['students']) }}</b><span>students benefiting</span></div>
            <div><b>{{ money_inr($stats['given'], false) }}</b><span>discount given</span></div>
        </div>
        <div class="sc-hero-actions">
            @if($canAssign)<a href="{{ route('admin.scholarships.assign') }}" class="btn btn-light border"><i class="ti ti-user-check me-1"></i> Assign to students</a>@endif
            @if($canManage)<button type="button" class="btn btn-primary sc-cta" wire:click="openModal"><i class="ti ti-circle-plus me-1"></i> New scholarship</button>@endif
        </div>
    </div>

    <!-- Filters -->
    <div class="sc-filters mb-3">
        <div class="sc-search"><i class="ti ti-search"></i><input type="search" class="form-control" placeholder="Search name or code…" wire:model.debounce.400ms="search"></div>
        @if($isSuperAdmin)
            <select class="form-select" wire:model="institute" style="max-width: 240px;">
                <option value="">All institutes</option>
                @foreach($institutes as $i)<option value="{{ $i->id }}">{{ $i->name }}</option>@endforeach
            </select>
        @endif
        <select class="form-select" wire:model="course" style="max-width: 260px;">
            <option value="">All courses</option>
            @foreach($filterCourses as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>@endforeach
        </select>
        <div class="sc-seg ms-md-auto">
            @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $k => $l)
                <button type="button" class="{{ $state === $k ? 'is-on' : '' }}" wire:click="$set('state', '{{ $k }}')">{{ $l }}</button>
            @endforeach
        </div>
    </div>

    <!-- Cards -->
    <div class="sc-grid">
        @forelse($scholarships as $s)
            @php $total = (float) ($given[$s->id] ?? 0); @endphp
            <div class="sc-card {{ $s->status ? '' : 'is-off' }}" wire:key="s-{{ $s->id }}">
                <div class="sc-card-top">
                    <div class="sc-value {{ $s->type === 'percentage' ? 'is-pct' : 'is-fixed' }}">
                        @if($s->type === 'percentage')
                            <b>{{ rtrim(rtrim(number_format((float) $s->value, 2), '0'), '.') }}<small>%</small></b><span>off</span>
                        @else
                            <b><small>₹</small>{{ number_format((float) $s->value) }}</b><span>/ {{ strtolower(optional($s->course)->period_label ?: 'semester') }}</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="sc-code">{{ $s->code }}</span>
                            @unless($s->status)<span class="sc-off">Inactive</span>@endunless
                        </div>
                        <h5 class="sc-name text-truncate" title="{{ $s->name }}">{{ $s->name }}</h5>
                        @if($isSuperAdmin)<div class="small text-muted text-truncate"><i class="ti ti-building"></i> {{ optional($s->institute)->name }}</div>@endif
                    </div>
                    @if($canManage)
                        <div class="dropdown">
                            <button class="btn btn-sm btn-icon btn-light" data-bs-toggle="dropdown" aria-label="Actions"><i class="ti ti-dots-vertical"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="javascript:void(0);" wire:click="edit({{ $s->id }})"><i class="ti ti-edit me-2"></i>Edit</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);" wire:click="toggleStatus({{ $s->id }})"><i class="ti ti-{{ $s->status ? 'toggle-left' : 'toggle-right' }} me-2"></i>{{ $s->status ? 'Deactivate' : 'Activate' }}</a></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0);" wire:click="confirmDelete({{ $s->id }})"><i class="ti ti-trash me-2"></i>Delete</a></li>
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="sc-facts">
                    <div><i class="ti ti-books"></i> <span class="text-truncate">{{ optional($s->course)->name }}</span> <span class="sc-chip">{{ optional($s->course)->code }}</span></div>
                    <div><i class="ti ti-users-group"></i> {{ $s->batch ? 'Batch ' . $s->batch->code : 'Every batch' }} · {{ $s->academicYear ? $s->academicYear->name : 'every year' }}</div>
                    <div><i class="ti ti-calendar-time"></i> {{ $s->periods_label }}{{ $s->applies_to === 'all_fees' ? ' + one-time fees' : '' }}</div>
                </div>
                <div class="sc-periods">
                    @foreach(range(1, max(1, (int) optional($s->course)->total_periods)) as $n)
                        <span class="{{ in_array($n, $s->periods, true) ? 'is-on' : '' }}" title="{{ optional($s->course)->periodName($n) }}">{{ $n }}</span>
                    @endforeach
                </div>
                @if($s->description)<p class="sc-desc">{{ $s->description }}</p>@endif
                <div class="sc-card-foot">
                    <div class="sc-metric"><b>{{ $s->students_count }}</b><span>{{ \Illuminate\Support\Str::plural('student', $s->students_count) }}</span></div>
                    <div class="sc-metric"><b>{{ money_inr($total, false) }}</b><span>discount given</span></div>
                    @if($canAssign && $s->status)
                        <a href="{{ route('admin.scholarships.assign', ['scholarship' => $s->id]) }}" class="btn btn-sm btn-outline-primary ms-auto"><i class="ti ti-user-plus me-1"></i> Assign</a>
                    @elseif($s->students_count)
                        <a href="{{ route('admin.scholarships.assign', ['scholarship' => $s->id, 'tab' => 'assigned']) }}" class="btn btn-sm btn-light border ms-auto">Students</a>
                    @endif
                </div>
            </div>
        @empty
            <div class="sc-empty">
                <span><i class="ti ti-award"></i></span>
                <div class="fw-semibold fs-16">{{ $search || $course ? 'No scholarship matches the filter' : 'No scholarships yet' }}</div>
                <div class="text-muted small mb-3">Create a scheme — e.g. <b>Merit 25%</b> on Semesters 1–2, or <b>Sports ₹5,000</b> per semester.</div>
                @if($canManage)<button type="button" class="btn btn-primary" wire:click="openModal"><i class="ti ti-circle-plus me-1"></i> New scholarship</button>@endif
            </div>
        @endforelse
    </div>

    <!-- Add / Edit modal -->
    @if($canManage)
    <div class="modal fade" id="scholarshipModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-primary bg-opacity-10 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-primary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center"><i class="ti ti-award text-primary fs-14"></i></div>
                        <div>
                            <h4 class="modal-title fw-semibold mb-0">{{ $recordId ? 'Edit scholarship' : 'New scholarship' }}</h4>
                            <small style="color: rgba(255, 255, 255, .85);">{{ $recordId ? 'Changes are re-applied to unpaid fees of students who have it' : 'Course, discount and the semesters it covers' }}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        {{-- Where --}}
                        <div class="col-12"><div class="sc-step"><span>1</span> Where it applies</div></div>
                        @if($isSuperAdmin)
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">Institute <span class="text-danger">*</span></label>
                                <select class="form-select @error('institute_id') is-invalid @enderror" wire:model="institute_id" @if($recordId) disabled @endif>
                                    <option value="">Select institute</option>
                                    @foreach($institutes as $i)<option value="{{ $i->id }}">{{ $i->name }}</option>@endforeach
                                </select>
                                @error('institute_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endif
                        <div class="col-md-{{ $isSuperAdmin ? 6 : 12 }}">
                            <label class="form-label small fw-medium">Course <span class="text-danger">*</span></label>
                            <select class="form-select @error('course_id') is-invalid @enderror" wire:model="course_id" @if($recordId) disabled @endif>
                                <option value="">{{ $formCourses->isEmpty() ? 'Select the institute first' : 'Select course' }}</option>
                                @foreach($formCourses as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>@endforeach
                            </select>
                            @error('course_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Batch</label>
                            <select class="form-select @error('batch_id') is-invalid @enderror" wire:model.defer="batch_id" @if(!$course_id) disabled @endif>
                                <option value="">Every batch</option>
                                @foreach($formBatches as $b)<option value="{{ $b->id }}">{{ $b->code }} — {{ $b->name }}</option>@endforeach
                            </select>
                            @error('batch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Academic year</label>
                            <select class="form-select" wire:model.defer="academic_year_id">
                                <option value="">Every academic year</option>
                                @foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach
                            </select>
                        </div>

                        {{-- What --}}
                        <div class="col-12 mt-4"><div class="sc-step"><span>2</span> Scholarship</div></div>
                        <div class="col-md-8">
                            <label class="form-label small fw-medium">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model.defer="name" placeholder="e.g. Merit scholarship">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase font-monospace @error('code') is-invalid @enderror" wire:model.defer="code" placeholder="MERIT25" maxlength="30">
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium d-block">Discount type <span class="text-danger">*</span></label>
                            <div class="sc-type">
                                <button type="button" class="{{ $type === 'percentage' ? 'is-on' : '' }}" wire:click="setType('percentage')"><i class="ti ti-percentage"></i> Percentage</button>
                                <button type="button" class="{{ $type === 'fixed' ? 'is-on' : '' }}" wire:click="setType('fixed')"><i class="ti ti-currency-rupee"></i> Fixed amount</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">{{ $type === 'percentage' ? 'Percentage off each fee' : 'Amount off per ' . strtolower(optional($formCourse)->period_label ?: 'semester') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                @if($type === 'fixed')<span class="input-group-text">₹</span>@endif
                                <input type="number" step="0.01" min="0" class="form-control @error('value') is-invalid @enderror" wire:model.lazy="value" placeholder="{{ $type === 'percentage' ? '25' : '5000' }}">
                                @if($type === 'percentage')<span class="input-group-text">%</span>@endif
                            </div>
                            @error('value')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        @if(is_numeric($value) && $value > 0)
                            <div class="col-12">
                                <div class="sc-example"><i class="ti ti-calculator"></i>
                                    @if($type === 'percentage')
                                        <span>Example: a ₹42,000 semester fee → <b>− {{ money_inr(round(42000 * min(100, (float) $value) / 100, 2), false) }}</b>, student pays <b>{{ money_inr(42000 - round(42000 * min(100, (float) $value) / 100, 2), false) }}</b>.</span>
                                    @else
                                        <span>Example: semester fees ₹40,000 + ₹10,000 → <b>− {{ money_inr(min(50000, (float) $value), false) }}</b> split 80 / 20 over the two lines, student pays <b>{{ money_inr(max(0, 50000 - (float) $value), false) }}</b>.</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- When --}}
                        <div class="col-12 mt-4"><div class="sc-step"><span>3</span> {{ \Illuminate\Support\Str::plural(optional($formCourse)->period_label ?: 'Semester') }} covered <span class="text-danger">*</span></div></div>
                        <div class="col-12">
                            @if($formCourse)
                                <div class="sc-period-pick @error('periods') is-invalid @enderror">
                                    @foreach(range(1, max(1, (int) $formCourse->total_periods)) as $n)
                                        <button type="button" class="{{ in_array((string) $n, $periods, true) ? 'is-on' : '' }}" wire:click="togglePeriod({{ $n }})">
                                            <small>{{ $formCourse->period_label ?: 'Semester' }}</small><b>{{ $n }}</b>
                                        </button>
                                    @endforeach
                                    <button type="button" class="sc-all" wire:click="allPeriods">{{ count($periods) === (int) $formCourse->total_periods ? 'Clear' : 'All' }}</button>
                                </div>
                                @error('periods')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            @else
                                <div class="small text-muted">Choose the course to pick its {{ strtolower(\Illuminate\Support\Str::plural('semester')) }}.</div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Applies to</label>
                            <select class="form-select" wire:model.defer="applies_to">
                                @foreach(\App\Models\Admin\Scholarship::APPLIES_TO as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="sc-status w-100">
                                <div><span class="fw-semibold small">Status</span><div class="small text-muted">Inactive: not offered for new assignments</div></div>
                                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" wire:model="status" value="1"></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Description (optional)</label>
                            <textarea rows="2" class="form-control" wire:model.defer="description" placeholder="Who qualifies, e.g. 90% and above in Class 12"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-light px-4 me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary px-4" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save"><i class="ti ti-device-floppy me-1"></i> {{ $recordId ? 'Update' : 'Create' }} scholarship</span>
                        <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scholarshipDeleteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center py-4">
            <i class="ti ti-alert-triangle text-danger fs-1 mb-3"></i>
            <h5>Delete this scholarship?</h5>
            <p class="text-muted mb-4">Only possible while no student has it. Otherwise set it inactive.</p>
            <div class="d-flex justify-content-center gap-2">
                <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger px-4" wire:click="delete">Yes, delete</button>
            </div>
        </div></div></div>
    </div>
    @endif

    @include('livewire.admin.scholarships.partials.styles')

    <script>
        document.addEventListener('livewire:load', function () {
            [['scholarship', 'scholarshipModal'], ['scholarship-delete', 'scholarshipDeleteModal']].forEach(([name, id]) => {
                window.addEventListener(`open-${name}-modal`, () => bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show());
                window.addEventListener(`close-${name}-modal`, () => { const m = bootstrap.Modal.getInstance(document.getElementById(id)); if (m) { m.hide(); } });
            });
        });
    </script>
</div>
