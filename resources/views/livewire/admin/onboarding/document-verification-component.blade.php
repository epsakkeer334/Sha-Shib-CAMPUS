@php
    $statusCards = [
        'all' => ['All students', $counts['all'], 'ti ti-users', 'all', 'Every submitted application'],
        'pending' => ['Pending review', $counts['pending'], 'ti ti-hourglass-high', 'warn', 'Documents waiting to be checked'],
        'rejected' => ['Rejected', $counts['rejected'], 'ti ti-file-x', 'bad', 'Re-upload needed or returned'],
        'approved' => ['Gate approved', $counts['approved'], 'ti ti-circle-check', 'ok', 'Documents gate passed'],
    ];
    $requiredTypes = collect($documentTypes)->filter(fn ($t) => $t[1]);
    $deadlineChip = function ($days) {
        if (is_null($days)) return [null, null];
        if ($days < 0) return ['dv-chip-bad', abs($days) . 'd overdue'];
        if ($days <= config('camp.deadline_warning_days', 7)) return ['dv-chip-warn', $days . 'd left'];
        return ['dv-chip-muted', $days . 'd left'];
    };
@endphp
<div class="content doc-verify">
    {{-- Header --}}
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Document verification</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Student Onboarding</li>
                    <li class="breadcrumb-item active">Document verification · Gate 1</li>
                </ol>
            </nav>
        </div>
    </div>

    {{-- Status cards (queues) --}}
    <div class="row g-3 mb-3">
        @foreach($statusCards as $key => [$label, $count, $icon, $tone, $hint])
            <div class="col-sm-6 col-xl-3">
                <button type="button" wire:click="$set('tab', '{{ $key }}')" class="dv-status dv-status-{{ $tone }} {{ $tab === $key ? 'is-active' : '' }}"
                        aria-pressed="{{ $tab === $key ? 'true' : 'false' }}">
                    <span class="dv-status-icon"><i class="{{ $icon }}"></i></span>
                    <span class="d-flex flex-column text-start">
                        <span class="dv-status-count">{{ number_format($count) }}</span>
                        <span class="dv-status-label">{{ $label }}</span>
                        <span class="dv-status-hint">{{ $hint }}</span>
                    </span>
                    @if($tab === $key)<i class="ti ti-chevron-right dv-status-arrow"></i>@endif
                </button>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        {{-- Student queue --}}
        <div class="col-xl-4 col-xxl-3">
            <div class="dv-panel h-100">
                <div class="dv-panel-head">
                    <span class="fw-semibold">{{ $statusCards[$tab][0] ?? 'Students' }}</span>
                    <span class="dv-count">{{ number_format($students->total()) }}</span>
                </div>
                <div class="p-2 border-bottom">
                    <div class="position-relative">
                        <i class="ti ti-search position-absolute top-50 translate-middle-y text-muted" style="left: 11px;"></i>
                        <input type="search" class="form-control form-control-sm ps-5" placeholder="Search name, phone or email" aria-label="Search students"
                               wire:model.debounce.400ms="search">
                    </div>
                </div>
                <div class="dv-queue">
                    @forelse($students as $item)
                        @php
                            $verified = $item->documents->where('verification_status', 'verified')->whereIn('document_type', $requiredTypes->keys())->pluck('document_type')->unique()->count();
                            $toReview = $item->documents->where('verification_status', 'pending')->count();
                            $percent = $requiredTypes->count() ? round($verified / $requiredTypes->count() * 100) : 0;
                            [$chipClass, $chipText] = $deadlineChip($item->er_number ? null : $item->days_to_deadline);
                            $itemGate = optional($item->approvals->firstWhere('gate', \App\Models\Admin\EnrollmentApproval::DOCUMENTS))->status;
                            $hasRejected = $item->documents->where('verification_status', 'rejected')->isNotEmpty();
                            $needsReview = $toReview > 0 && $itemGate === 'pending';
                            [$stateClass, $stateText] = match (true) {
                                $itemGate === 'approved' => ['dv-state-ok', 'Verified'],
                                $itemGate === 'rejected' => ['dv-state-bad', 'Returned'],
                                $needsReview => ['dv-state-warn', $toReview . ' to review'],
                                $hasRejected => ['dv-state-bad', 'Re-upload'],
                                $verified >= $requiredTypes->count() => ['dv-state-ok', 'All verified'],
                                default => ['dv-state-muted', 'Awaiting upload'],
                            };
                        @endphp
                        <button type="button" wire:click="select({{ $item->id }})"
                                class="dv-queue-item {{ $needsReview ? 'needs-review' : '' }} {{ $student && $student->id === $item->id ? 'is-active' : '' }}">
                            <span class="dv-avatar">{{ $item->initials }}</span>
                            <span class="flex-grow-1 min-w-0">
                                <span class="d-flex justify-content-between align-items-center gap-2">
                                    <span class="fw-semibold text-truncate text-dark">{{ $item->full_name }}</span>
                                    @if($chipText)<span class="dv-chip {{ $chipClass }}">{{ $chipText }}</span>@endif
                                </span>
                                <span class="d-flex align-items-center gap-2 small text-muted mt-1 flex-wrap">
                                    <span class="dv-course">{{ optional($item->course)->code }}</span>
                                    <span class="dv-state {{ $stateClass }}">
                                        @if($stateClass === 'dv-state-ok')<i class="ti ti-circle-check"></i>@elseif($needsReview)<i class="ti ti-clock"></i>@endif
                                        {{ $stateText }}
                                    </span>
                                    @if($item->er_number)<span class="text-muted">{{ $item->er_number }}</span>@endif
                                </span>
                                <span class="dv-progress mt-2" title="{{ $verified }} of {{ $requiredTypes->count() }} required documents verified">
                                    <span style="width: {{ $percent }}%;"></span>
                                </span>
                            </span>
                        </button>
                    @empty
                        <div class="text-center text-muted py-5 small">
                            <i class="ti ti-inbox fs-1 d-block mb-1 opacity-50"></i> Nothing in this queue.
                        </div>
                    @endforelse
                </div>

                {{-- Pager (20 per page; students needing review always come first) --}}
                @if($students->hasPages())
                    @php $pageName = \App\Http\Livewire\Admin\Onboarding\DocumentVerificationComponent::PAGE_NAME; @endphp
                    <div class="dv-pager">
                        <span class="small text-muted">{{ $students->firstItem() }}–{{ $students->lastItem() }} of {{ number_format($students->total()) }}</span>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-light border" wire:click="previousPage('{{ $pageName }}')"
                                    @if($students->onFirstPage()) disabled @endif aria-label="Previous page"><i class="ti ti-chevron-left"></i></button>
                            <span class="small px-1 text-nowrap">Page {{ $students->currentPage() }} of {{ $students->lastPage() }}</span>
                            <button type="button" class="btn btn-sm btn-light border" wire:click="nextPage('{{ $pageName }}')"
                                    @if(!$students->hasMorePages()) disabled @endif aria-label="Next page"><i class="ti ti-chevron-right"></i></button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Selected student --}}
        <div class="col-xl-8 col-xxl-9">
            @if(!$student)
                <div class="dv-panel dv-empty">
                    <i class="ti ti-file-search"></i>
                    <div class="fw-semibold">Select a student</div>
                    <div class="small text-muted">Pick someone from the queue to review their KYC documents.</div>
                </div>
            @else
                @php
                    $verifiedReq = $student->documents->where('verification_status', 'verified')->whereIn('document_type', $requiredTypes->keys())->pluck('document_type')->unique()->count();
                    $percentReq = $requiredTypes->count() ? round($verifiedReq / $requiredTypes->count() * 100) : 0;
                    $locked = optional($gate)->status === 'approved';
                @endphp

                {{-- Student header --}}
                <div class="dv-panel mb-3">
                    <div class="dv-student">
                        <span class="dv-avatar dv-avatar-lg">{{ $student->initials }}</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <a href="{{ route('admin.students.edit', $student->id) }}" class="fs-16 fw-semibold text-dark">{{ $student->full_name }}</a>
                                {!! $student->status_html !!}
                            </div>
                            <div class="small text-muted text-truncate">
                                {{ optional($student->course)->name }} · {{ $student->email }} · {{ $student->phone }}
                            </div>
                            <div class="d-flex gap-3 small mt-1 flex-wrap">
                                <span class="text-muted"><i class="ti ti-calendar"></i> Joined {{ $student->formatted_joining_date }}</span>
                                @php [$chipClass, $chipText] = $deadlineChip($student->er_number ? null : $student->days_to_deadline); @endphp
                                <span class="text-muted"><i class="ti ti-flag"></i> Deadline {{ $student->formatted_onboarding_deadline }}</span>
                                @if($chipText)<span class="dv-chip {{ $chipClass }}">{{ $chipText }}</span>@endif
                            </div>
                        </div>
                        <div class="dv-ring" style="--p: {{ $percentReq }};" title="{{ $verifiedReq }} of {{ $requiredTypes->count() }} verified">
                            <span>{{ $verifiedReq }}/{{ $requiredTypes->count() }}</span>
                        </div>
                    </div>
                </div>

                {{-- Documents --}}
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-semibold mb-0">KYC documents</h6>
                    @if($student->missingRequiredDocuments() && !$locked)
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="remind" wire:loading.attr="disabled">
                            <i class="ti ti-bell me-1"></i> Remind about missing documents
                        </button>
                    @endif
                </div>
                <div class="row g-3 mb-3">
                    @foreach($documentTypes as $type => [$label, $required])
                        @php $typeDocs = $documents->get($type, collect()); @endphp
                        @if($typeDocs->isEmpty())
                            @if($required)
                                <div class="col-md-6 col-xxl-3">
                                    <div class="dv-doc dv-doc-missing">
                                        <i class="ti ti-file-off"></i>
                                        <span class="fw-semibold">{{ $label }}</span>
                                        <span class="small text-muted">Not uploaded yet</span>
                                    </div>
                                </div>
                            @endif
                        @else
                            @foreach($typeDocs as $doc)
                                @include('livewire.admin.onboarding.partials.document-card', ['doc' => $doc, 'locked' => $locked])
                            @endforeach
                        @endif
                    @endforeach
                </div>

                {{-- Gate 1 --}}
                <div class="dv-panel dv-gate {{ $locked ? 'dv-gate-ok' : '' }}">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div class="d-flex gap-3">
                            <span class="dv-gate-icon"><i class="ti {{ $locked ? 'ti-shield-check' : 'ti-shield' }}"></i></span>
                            <div>
                                <div class="fw-semibold">Gate 1 · Admin document verification</div>
                                <div class="small text-muted">Approve once every required document is verified · {{ $verifiedReq }} of {{ $requiredTypes->count() }} verified</div>
                                @if($gate && $gate->status !== 'pending')
                                    <div class="small mt-1">
                                        <span class="dv-badge {{ $gate->status === 'approved' ? 'dv-badge-ok' : 'dv-badge-bad' }}">{{ ucfirst($gate->status) }}</span>
                                        <span class="text-muted">{{ optional($gate->approver)->name }} · {{ optional($gate->approved_at)->format('d M Y, h:i A') }}</span>
                                        @if($gate->remarks)<span class="text-muted"> — “{{ $gate->remarks }}”</span>@endif
                                    </div>
                                @elseif($gate && $gateBlocker)
                                    <div class="small mt-1" style="color: #B45309;"><i class="ti ti-info-circle"></i> {{ $gateBlocker }}</div>
                                @endif
                            </div>
                        </div>
                        @if($gate && $gate->status === 'pending')
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rejectGateBox">
                                    <i class="ti ti-arrow-back-up me-1"></i> Return
                                </button>
                                <button type="button" class="btn btn-success" wire:click="approveGate" wire:loading.attr="disabled"
                                        @if($gateBlocker) disabled title="{{ $gateBlocker }}" @endif>
                                    <i class="ti ti-check me-1"></i> Approve gate
                                </button>
                            </div>
                        @endif
                    </div>
                    @if($gate && $gate->status === 'pending')
                        <div class="collapse mt-3" id="rejectGateBox" wire:ignore.self>
                            <label class="form-label small fw-medium" for="gateRemarks">Reason for returning the application *</label>
                            <textarea id="gateRemarks" class="form-control @error('gateRemarks') is-invalid @enderror" rows="2" wire:model.defer="gateRemarks"
                                      placeholder="Explain what the student must correct"></textarea>
                            @error('gateRemarks') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <button type="button" class="btn btn-danger btn-sm mt-2" wire:click="rejectGate">Return application to the student</button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <style>
        .doc-verify .min-w-0 { min-width: 0; }
        /* status cards */
        .doc-verify .dv-status { width: 100%; display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); position: relative; transition: border-color .15s, box-shadow .15s; }
        .doc-verify .dv-status:hover { box-shadow: 0 4px 12px rgba(16, 24, 40, .08); }
        .doc-verify .dv-status-icon { width: 46px; height: 46px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .doc-verify .dv-status-count { font-size: 24px; font-weight: 700; line-height: 1.1; color: #111827; }
        .doc-verify .dv-status-label { font-size: 14px; font-weight: 600; color: #374151; }
        .doc-verify .dv-status-hint { font-size: 12px; color: #9CA3AF; }
        .doc-verify .dv-status-arrow { position: absolute; right: 16px; font-size: 18px; }
        .doc-verify .dv-status-warn .dv-status-icon { background: #FEF3C7; color: #B45309; }
        .doc-verify .dv-status-bad .dv-status-icon { background: #FEE2E2; color: #DC2626; }
        .doc-verify .dv-status-ok .dv-status-icon { background: #DCFCE7; color: #15803D; }
        .doc-verify .dv-status-warn.is-active { border-color: #F59E0B; box-shadow: 0 0 0 3px rgba(245, 158, 11, .15); }
        .doc-verify .dv-status-bad.is-active { border-color: #EF4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, .12); }
        .doc-verify .dv-status-ok.is-active { border-color: #22C55E; box-shadow: 0 0 0 3px rgba(34, 197, 94, .14); }
        .doc-verify .dv-status-warn.is-active .dv-status-arrow { color: #B45309; }
        .doc-verify .dv-status-bad.is-active .dv-status-arrow { color: #DC2626; }
        .doc-verify .dv-status-ok.is-active .dv-status-arrow { color: #15803D; }
        /* panels */
        .doc-verify .dv-panel { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        .doc-verify .dv-panel-head { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border-bottom: 1px solid #F1F2F4; }
        .doc-verify .dv-count { min-width: 26px; height: 22px; padding: 0 8px; border-radius: 999px; background: #F3F4F6; color: #374151; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; }
        .doc-verify .dv-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 64px 16px; text-align: center; }
        .doc-verify .dv-empty i { font-size: 40px; color: #D1D5DB; }
        /* queue */
        .doc-verify .dv-queue { max-height: 68vh; overflow-y: auto; }
        .doc-verify .dv-pager { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 10px 12px; border-top: 1px solid #F1F2F4; background: #FAFAFA; border-radius: 0 0 12px 12px; }
        .doc-verify .dv-queue-item { width: 100%; display: flex; gap: 10px; padding: 12px 14px; border: 0; border-bottom: 1px solid #F3F4F6; background: #fff; text-align: left; border-left: 3px solid transparent; }
        .doc-verify .dv-queue-item:hover { background: #F9FAFB; }
        .doc-verify .dv-queue-item.needs-review { background: #FFFBEB; border-left-color: #F59E0B; }
        .doc-verify .dv-queue-item.needs-review .text-dark { font-weight: 700 !important; }
        .doc-verify .dv-queue-item.needs-review:hover { background: #FEF3C7; }
        .doc-verify .dv-queue-item.is-active { background: #FFF7F2; border-left-color: #F26522; }
        .doc-verify .dv-state { display: inline-flex; align-items: center; gap: 3px; padding: 0 7px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .doc-verify .dv-state-ok { background: #DCFCE7; color: #15803D; }
        .doc-verify .dv-state-warn { background: #FEF3C7; color: #B45309; }
        .doc-verify .dv-state-bad { background: #FEE2E2; color: #DC2626; }
        .doc-verify .dv-state-muted { background: #F3F4F6; color: #6B7280; }
        .doc-verify .dv-status-all .dv-status-icon { background: #EEF2FF; color: #4338CA; }
        .doc-verify .dv-status-all.is-active { border-color: #6366F1; box-shadow: 0 0 0 3px rgba(99, 102, 241, .14); }
        .doc-verify .dv-status-all.is-active .dv-status-arrow { color: #4338CA; }
        .doc-verify .dv-avatar { width: 36px; height: 36px; border-radius: 50%; background: #FEF0E7; color: #F26522; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .doc-verify .dv-avatar-lg { width: 52px; height: 52px; font-size: 17px; }
        .doc-verify .dv-course { padding: 0 6px; border-radius: 5px; background: #EEF2FF; color: #4338CA; font-weight: 500; font-size: 11px; }
        .doc-verify .dv-chip { padding: 1px 7px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .doc-verify .dv-chip-bad { background: #FEE2E2; color: #DC2626; }
        .doc-verify .dv-chip-warn { background: #FEF3C7; color: #B45309; }
        .doc-verify .dv-chip-muted { background: #F3F4F6; color: #6B7280; }
        .doc-verify .dv-progress { display: block; height: 4px; border-radius: 4px; background: #F1F2F4; overflow: hidden; }
        .doc-verify .dv-progress span { display: block; height: 100%; background: #22C55E; border-radius: 4px; }
        /* student header */
        .doc-verify .dv-student { display: flex; align-items: center; gap: 14px; padding: 14px 18px; }
        .doc-verify .dv-ring { --p: 0; width: 54px; height: 54px; border-radius: 50%; flex-shrink: 0; display: grid; place-items: center; background: conic-gradient(#22C55E calc(var(--p) * 1%), #F1F2F4 0); }
        .doc-verify .dv-ring span { width: 42px; height: 42px; border-radius: 50%; background: #fff; display: grid; place-items: center; font-size: 12px; font-weight: 700; color: #111827; }
        /* document cards (compact) */
        .doc-verify .dv-doc { height: 100%; background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        .doc-verify .dv-doc-pending { border-color: #FCD34D; }
        .doc-verify .dv-doc-rejected { border-color: #FCA5A5; }
        .doc-verify .dv-doc-preview { position: relative; height: 96px; background: #F3F4F6; display: flex; align-items: center; justify-content: center; overflow: hidden; text-decoration: none; }
        .doc-verify .dv-doc-preview img { width: 100%; height: 100%; object-fit: cover; }
        .doc-verify .dv-doc-pdf { display: flex; flex-direction: column; align-items: center; color: #DC2626; font-size: 11px; font-weight: 600; }
        .doc-verify .dv-doc-pdf i { font-size: 30px; }
        .doc-verify .dv-doc-open { position: absolute; right: 6px; bottom: 6px; padding: 1px 7px; border-radius: 6px; background: rgba(17, 24, 39, .7); color: #fff; font-size: 11px; opacity: 0; transition: opacity .15s; }
        .doc-verify .dv-doc-preview:hover .dv-doc-open { opacity: 1; }
        .doc-verify .dv-doc-body { padding: 10px 12px; display: flex; flex-direction: column; gap: 8px; }
        .doc-verify .dv-doc-title { font-size: 13.5px; font-weight: 600; color: #111827; }
        .doc-verify .dv-doc-meta { font-size: 11.5px; color: #9CA3AF; }
        .doc-verify .dv-doc-note { font-size: 12px; color: #6B7280; }
        .doc-verify .dv-remarks { font-size: 12.5px; resize: vertical; min-height: 32px; }
        .doc-verify .dv-doc-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .doc-verify .dv-doc-actions .btn { font-size: 12.5px; padding: 4px 8px; }
        .doc-verify .dv-doc-missing { align-items: center; justify-content: center; gap: 2px; padding: 22px 12px; min-height: 150px; border-style: dashed; background: #FAFAFA; text-align: center; box-shadow: none; }
        .doc-verify .dv-doc-missing i { font-size: 26px; color: #D1D5DB; }
        .doc-verify .dv-badge { padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .doc-verify .dv-badge-ok { background: #DCFCE7; color: #15803D; }
        .doc-verify .dv-badge-bad { background: #FEE2E2; color: #DC2626; }
        .doc-verify .dv-badge-warn { background: #FEF3C7; color: #B45309; }
        .doc-verify .dv-badge-info { background: #DBEAFE; color: #1D4ED8; }
        /* gate */
        .doc-verify .dv-gate { padding: 16px 18px; }
        .doc-verify .dv-gate-icon { width: 40px; height: 40px; border-radius: 10px; background: #F3F4F6; color: #6B7280; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .doc-verify .dv-gate-ok { border-color: #86EFAC; background: #F7FEF9; }
        .doc-verify .dv-gate-ok .dv-gate-icon { background: #DCFCE7; color: #15803D; }
    </style>
</div>
