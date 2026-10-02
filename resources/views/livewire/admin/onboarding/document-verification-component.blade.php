<div class="content">
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
        @include('livewire.admin.onboarding.partials.queue-tabs', ['tabs' => [
            'pending' => 'Pending · ' . $counts['pending'],
            'rejected' => 'Rejected · ' . $counts['rejected'],
            'approved' => 'Gate approved',
        ]])
    </div>

    <div class="row g-3">
        {{-- Students waiting --}}
        <div class="col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-2">
                    <label class="visually-hidden" for="docSearch">Search students</label>
                    <input id="docSearch" type="search" class="form-control mb-2" placeholder="Search name, phone or email" wire:model.debounce.400ms="search">
                    <div class="list-group list-group-flush onboarding-queue">
                        @forelse($students as $item)
                            @php
                                $toReview = $item->documents->where('verification_status', 'pending')->count();
                                $total = $item->documents->count();
                                $days = $item->days_to_deadline;
                            @endphp
                            <button type="button" wire:click="select({{ $item->id }})"
                                    class="list-group-item list-group-item-action py-3 {{ $student && $student->id === $item->id ? 'active-queue' : '' }}">
                                <span class="d-flex justify-content-between gap-2">
                                    <span class="fw-semibold">{{ $item->full_name }}</span>
                                    @if(!is_null($days))
                                        <span class="small fw-medium {{ $days < 0 ? 'text-danger' : ($days <= 7 ? 'text-danger' : 'text-muted') }}">
                                            {{ $days < 0 ? abs($days) . ' days overdue' : $days . ' days' }}
                                        </span>
                                    @endif
                                </span>
                                <span class="small text-muted d-block">
                                    {{ optional($item->course)->code }} ·
                                    @if($tab === 'approved') ER {{ $item->er_number ?: 'pending' }} @else {{ $toReview }} of {{ $total }} to review @endif
                                </span>
                            </button>
                        @empty
                            <div class="text-center text-muted py-5 small"><i class="ti ti-mood-empty fs-2 d-block mb-1"></i> Nothing in this queue.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Selected student --}}
        <div class="col-xl-8">
            @if(!$student)
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center text-muted py-5">
                        <i class="ti ti-hand-click fs-1 d-block mb-2"></i> Select a student to review their documents.
                    </div>
                </div>
            @else
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar avatar-md rounded-circle bg-soft-success text-success fw-semibold d-flex align-items-center justify-content-center">{{ $student->initials }}</span>
                            <div>
                                <a href="{{ route('admin.students.edit', $student->id) }}" class="fw-semibold fs-16 text-dark">{{ $student->full_name }}</a>
                                <div class="small text-muted">{{ optional($student->course)->name }} · joined {{ $student->formatted_joining_date }} · deadline {{ $student->formatted_onboarding_deadline }}</div>
                            </div>
                        </div>
                        {!! $student->status_html !!}
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    @foreach($documentTypes as $type => [$label, $required])
                        @php $typeDocs = $documents->get($type, collect()); @endphp
                        @if($typeDocs->isEmpty())
                            @if($required)
                                <div class="col-md-6">
                                    <div class="doc-card doc-card-missing h-100 d-flex flex-column align-items-center justify-content-center text-center p-4">
                                        <span class="fw-semibold">{{ $label }}</span>
                                        <span class="small text-muted">Not uploaded yet</span>
                                    </div>
                                </div>
                            @endif
                        @else
                            @foreach($typeDocs as $doc)
                                @include('livewire.admin.onboarding.partials.document-card', ['doc' => $doc, 'locked' => optional($gate)->status === 'approved'])
                            @endforeach
                        @endif
                    @endforeach
                </div>

                @if($student->missingRequiredDocuments() && optional($gate)->status !== 'approved')
                    <div class="d-flex justify-content-end mb-3">
                        <button type="button" class="btn btn-outline-secondary" wire:click="remind" wire:loading.attr="disabled">
                            <i class="ti ti-bell me-1"></i> Send reminder for missing documents
                        </button>
                    </div>
                @endif

                {{-- Gate 1 --}}
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div>
                                <div class="fw-semibold">Gate 1 · Admin document verification</div>
                                @php $req = collect($documentTypes)->filter(fn ($t) => $t[1]); @endphp
                                <div class="small text-muted">
                                    Approve once every required document is verified.
                                    {{ $student->documents->where('verification_status', 'verified')->whereIn('document_type', $req->keys())->pluck('document_type')->unique()->count() }}
                                    of {{ $req->count() }} verified.
                                </div>
                                @if($gate && $gate->status !== 'pending')
                                    <div class="small mt-1">
                                        <span class="badge badge-soft-{{ $gate->status === 'approved' ? 'success' : 'danger' }}">{{ ucfirst($gate->status) }}</span>
                                        {{ optional($gate->approver)->name }} · {{ optional($gate->approved_at)->format('d M Y, h:i A') }}
                                        @if($gate->remarks) — “{{ $gate->remarks }}” @endif
                                    </div>
                                @endif
                            </div>
                            @if($gate && $gate->status === 'pending')
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rejectGateBox">Reject gate</button>
                                    <button type="button" class="btn btn-success" wire:click="approveGate" wire:loading.attr="disabled"
                                            @if($gateBlocker) disabled title="{{ $gateBlocker }}" @endif>
                                        <i class="ti ti-check me-1"></i> Approve gate
                                    </button>
                                </div>
                            @endif
                        </div>
                        @if($gate && $gate->status === 'pending')
                            @if($gateBlocker)<div class="small text-muted mt-2"><i class="ti ti-info-circle me-1"></i>{{ $gateBlocker }}</div>@endif
                            <div class="collapse mt-3" id="rejectGateBox" wire:ignore.self>
                                <label class="form-label small fw-medium" for="gateRemarks">Reason for returning the application *</label>
                                <textarea id="gateRemarks" class="form-control @error('gateRemarks') is-invalid @enderror" rows="2" wire:model.defer="gateRemarks"
                                          placeholder="Explain what the student must correct"></textarea>
                                @error('gateRemarks') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <button type="button" class="btn btn-danger mt-2" wire:click="rejectGate">Return application</button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('livewire.admin.onboarding.partials.styles')
</div>
