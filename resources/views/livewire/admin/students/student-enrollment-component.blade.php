@php
    use App\Models\Admin\EnrollmentApproval;

    $gateLinks = [
        EnrollmentApproval::DOCUMENTS => auth()->user()->can('onboarding.verify_documents') ? route('admin.onboarding.documents', ['student' => $student->id]) : null,
        EnrollmentApproval::FEES => auth()->user()->can('payments.verify') ? route('admin.onboarding.payments', ['tab' => 'gate']) : null,
    ];
    $gateTone = fn ($gate) => ['approved' => 'ok', 'rejected' => 'bad', 'pending' => 'warn'][optional($gate)->status] ?? 'muted';
    $gateText = fn ($gate) => ['approved' => 'Approved', 'rejected' => 'Rejected', 'pending' => 'Pending'][optional($gate)->status] ?? 'Not submitted';

    $cardIssued = $card && $card->tm_signature_status === 'physically_signed';
    // Journey: Gate 1 → Gate 2 → ER number → ER form archived → ID card issued
    $journey = [
        ['Documents', 'Gate 1', optional($gates[EnrollmentApproval::DOCUMENTS])->status === 'approved', optional(optional($gates[EnrollmentApproval::DOCUMENTS])->approved_at)->format('d M')],
        ['Fees', 'Gate 2', optional($gates[EnrollmentApproval::FEES])->status === 'approved', optional(optional($gates[EnrollmentApproval::FEES])->approved_at)->format('d M')],
        ['ER number', 'Issued', (bool) $student->er_number, optional(optional($form)->generated_at)->format('d M')],
        ['ER form', 'Archived', (bool) optional($form)->archived_at, optional(optional($form)->archived_at)->format('d M')],
        ['ID card', 'Issued', $cardIssued, optional(optional($card)->issue_date)->format('d M')],
    ];
    $journeyDone = collect($journey)->where(2, true)->count();
    $current = collect($journey)->search(fn ($s) => !$s[2]);

    $backUrl = $fromQueue ? route('admin.onboarding.enrollment') : route('admin.students');
    $backLabel = $fromQueue ? 'ER & ID cards' : 'All students';
    $check = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7.5 L6 10 L11 4"></path></svg>';
@endphp
<div class="content er-ui er-detail">
    {{-- Header --}}
    <div class="er-hero er-profile mb-3">
        <div class="d-flex align-items-center gap-3 min-w-0">
            @if($photo && $photo->is_image)
                <img src="{{ route('admin.students.documents.show', $photo->id) }}" class="er-profile-photo" alt="Photo of {{ $student->full_name }}">
            @else
                <span class="er-profile-photo er-profile-initials">{{ $student->initials }}</span>
            @endif
            <div class="min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h2 class="mb-0 fw-bold">{{ $student->full_name }}</h2>
                    {!! $student->status_html !!}
                </div>
                <nav>
                    <ol class="breadcrumb mb-1 sl-crumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item"><a href="{{ $backUrl }}">{{ $backLabel }}</a></li>
                        <li class="breadcrumb-item active">ER &amp; ID card</li>
                    </ol>
                </nav>
                <div class="er-meta">
                    @if($student->course)<span><span class="er-code">{{ $student->course->code }}</span> {{ $student->course->name }}</span>@endif
                    @if($student->institute)<span><i class="ti ti-building"></i> {{ $student->institute->name }} @if($student->institute->code)<span class="er-inst-code ms-1">{{ $student->institute->code }}</span>@endif</span>@endif
                </div>
                <div class="er-hchips">
                    <span class="er-hchip"><i class="ti ti-phone"></i> {{ $student->phone ?: '—' }}</span>
                    <span class="er-hchip"><i class="ti ti-calendar"></i> Joined {{ $student->formatted_joining_date }}</span>
                    @if($student->current_period_label)<span class="er-hchip er-hchip-period"><i class="ti ti-calendar-time"></i> {{ $student->current_period_label }}</span>@endif
                </div>
            </div>
        </div>
        <div class="er-number-card">
            <span class="er-number-label">ER number</span>
            @if($student->er_number)
                <span class="er-number-value">{{ $student->er_number }}</span>
                <span class="er-number-sub">Issued {{ optional(optional($form)->generated_at)->format('d M Y') }}</span>
            @else
                <span class="er-number-value er-number-none">Not issued yet</span>
                <span class="er-number-sub">Issued automatically after Gate 1 &amp; Gate 2</span>
            @endif
        </div>
    </div>

    @include('livewire.admin.students.partials.student-nav', ['student' => $student, 'active' => 'enrollment', 'fromQueue' => $fromQueue])

    {{-- Journey --}}
    <div class="er-panel mb-3">
        <div class="er-panel-head">
            <div class="er-panel-title">Enrollment progress <span class="er-count">{{ $journeyDone }}/{{ count($journey) }}</span></div>
            <a href="{{ $backUrl }}" class="btn btn-sm btn-light"><i class="ti ti-arrow-left me-1"></i> Back to {{ $backLabel }}</a>
        </div>
        <ol class="er-journey">
            @foreach($journey as $i => [$title, $sub, $done, $date])
                <li class="{{ $done ? 'is-done' : ($i === $current ? 'is-current' : '') }}">
                    <span class="er-journey-dot">{!! $done ? $check : $i + 1 !!}</span>
                    <span class="er-journey-title">{{ $title }}</span>
                    <span class="er-journey-sub">{{ $done ? ($date ? $sub . ' · ' . $date : $sub) : ($i === $current ? 'In progress' : 'Waiting') }}</span>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="row g-3">
        <div class="col-xxl-8">
            @if($form || $card)
                <div class="row g-3">
                    {{-- ER request form --}}
                    <div class="col-lg-6">
                        @php
                            [$formLabel, $formTone] = match ($form->status) {
                                'archived' => ['Archived', 'ok'],
                                'signed' => ['Signed — to archive', 'info'],
                                'printed' => ['Awaiting TM signature', 'warn'],
                                default => ['To print', 'muted'],
                            };
                            $steps = [
                                ['Form generated', optional($form->generated_at)->format('d M Y, H:i'), true],
                                ['Printed', $form->printed_at ? $form->printed_at->format('d M Y, H:i') : 'Print the form for the TM to sign', (bool) $form->printed_at],
                                ['Signed by Training Manager', $form->tm_signed_at ? optional($form->signer)->name . ' · ' . $form->tm_signed_at->format('d M Y, H:i') : 'Mark once the TM has signed the printed form', $form->tm_signature_status === 'physically_signed'],
                                ['Archived in physical file', $form->archived_at ? optional($form->archiver)->name . ' · ' . $form->archived_at->format('d M Y, H:i') : 'Confirms the signed form is filed', (bool) $form->archived_at],
                            ];
                            $currentFound = false;
                        @endphp
                        <div class="er-panel h-100 d-flex flex-column">
                            <div class="er-panel-head">
                                <div class="er-panel-title"><span class="er-title-icon er-title-icon-warn"><i class="ti ti-file-text"></i></span> ER request form</div>
                                <span class="er-chip er-chip-{{ $formTone }}">{{ $formLabel }}</span>
                            </div>
                            <div class="p-3 d-flex flex-column gap-3 flex-grow-1">
                                <ol class="er-timeline">
                                    @foreach($steps as [$title, $sub, $done])
                                        @php $isCurrent = !$done && !$currentFound; if ($isCurrent) { $currentFound = true; } @endphp
                                        <li class="{{ $done ? 'is-done' : ($isCurrent ? 'is-current' : '') }}">
                                            <span class="er-timeline-dot">{!! $done ? $check : '' !!}</span>
                                            <span class="d-flex flex-column min-w-0">
                                                <span class="fw-semibold">{{ $title }}</span>
                                                <span class="er-sub d-block">{{ $sub }}</span>
                                            </span>
                                        </li>
                                    @endforeach
                                </ol>
                                <div class="d-flex gap-2 flex-wrap mt-auto pt-2 border-top">
                                    <a href="{{ route('admin.students.er-form', $student->id) }}" target="_blank" class="btn btn-outline-secondary"><i class="ti ti-printer me-1"></i> Open form</a>
                                    @if($canManage)
                                        @if(!$form->printed_at)
                                            <button type="button" class="btn btn-primary" wire:click="formPrinted" wire:loading.attr="disabled"><i class="ti ti-printer me-1"></i> Mark printed</button>
                                        @elseif($form->tm_signature_status !== 'physically_signed')
                                            @if($canSign)
                                                @if($chooseSigner || $trainingManagers->isEmpty())
                                                    @include('livewire.admin.students.partials.tm-signer', ['model' => 'formSignerId', 'managers' => $trainingManagers])
                                                @endif
                                                <button type="button" class="btn btn-success" wire:click="formSigned" wire:loading.attr="disabled"><i class="ti ti-signature me-1"></i> Mark TM signed</button>
                                            @else
                                                <span class="small text-muted align-self-center"><i class="ti ti-hourglass-high"></i> Waiting for the Training Manager's signature</span>
                                            @endif
                                        @elseif(!$form->archived_at)
                                            <button type="button" class="btn btn-success" wire:click="formArchived" wire:loading.attr="disabled"><i class="ti ti-archive me-1"></i> Confirm archived</button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ID card --}}
                    <div class="col-lg-6">
                        @php
                            [$cardLabel, $cardTone] = $cardIssued ? ['Issued', 'ok']
                                : ($card->status === 'reprinted' ? ['Reprint — to sign', 'warn'] : ($card->print_count ? ['Printed — to sign', 'info'] : ['To print', 'muted']));
                        @endphp
                        <div class="er-panel h-100 d-flex flex-column">
                            <div class="er-panel-head">
                                <div class="er-panel-title"><span class="er-title-icon er-title-icon-info"><i class="ti ti-id"></i></span> Student ID card</div>
                                <span class="er-chip er-chip-{{ $cardTone }}">{{ $cardLabel }}</span>
                            </div>
                            <div class="p-3 d-flex flex-column gap-3 flex-grow-1">
                                <div class="er-card-stage">
                                    @include('livewire.admin.students.partials.id-card', ['student' => $student, 'card' => $card, 'photo' => $photo])
                                </div>
                                <div class="er-card-facts">
                                    <div><span>Printed</span><strong>{{ $card->print_count }}×</strong></div>
                                    <div><span>Issued</span><strong>{{ $card->issue_date ? $card->issue_date->format('d M Y') : '—' }}</strong></div>
                                    <div><span>Signed by</span><strong class="er-ellipsis">{{ optional($card->signer)->name ?? '—' }}</strong></div>
                                </div>
                                <div class="er-note"><i class="ti ti-info-circle"></i> The card is valid only after the Training Manager signs it by hand.</div>
                                <div class="d-flex gap-2 flex-wrap mt-auto pt-2 border-top">
                                    <a href="{{ route('admin.students.id-card', $student->id) }}" target="_blank" class="btn btn-outline-secondary" title="Opens the card; each print is counted automatically">
                                        <i class="ti ti-printer me-1"></i> Print card
                                    </a>
                                    @if($canManage)
                                        <button type="button" class="btn btn-link btn-sm text-decoration-none px-1" wire:click="cardPrinted" wire:loading.attr="disabled"
                                                title="Use only if a print was not counted automatically">
                                            <i class="ti ti-plus"></i> Record print
                                        </button>
                                    @endif
                                    @if($canManage)
                                        @if(!$cardIssued && !$canSign)
                                            <span class="small text-muted align-self-center"><i class="ti ti-hourglass-high"></i> Waiting for the Training Manager to sign &amp; issue</span>
                                        @elseif(!$cardIssued)
                                            @if($chooseSigner || $trainingManagers->isEmpty())
                                                @include('livewire.admin.students.partials.tm-signer', ['model' => 'cardSignerId', 'managers' => $trainingManagers])
                                            @endif
                                            <button type="button" class="btn btn-success" wire:click="cardIssued" wire:loading.attr="disabled" @if(!$card->print_count) disabled title="Print the card first" @endif>
                                                <i class="ti ti-signature me-1"></i> Mark signed &amp; issued
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reprintModal"><i class="ti ti-refresh me-1"></i> Reprint</button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="er-panel">
                    <div class="er-empty">
                        <span class="er-empty-icon"><i class="ti ti-id-badge-2"></i></span>
                        <div class="fw-semibold">ER form and ID card not ready yet</div>
                        <div class="small text-muted">They are prepared automatically once the ER number is issued after Gate 1 and Gate 2.</div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Side: gates, student details, fees --}}
        <div class="col-xxl-4">
            <div class="row g-3">
                <div class="col-md-6 col-xxl-12">
                    <div class="er-panel">
                        <div class="er-panel-head"><div class="er-panel-title"><span class="er-title-icon er-title-icon-ok"><i class="ti ti-shield-check"></i></span> Approval gates</div></div>
                        @foreach($gates as $key => $gate)
                            @php [$label, $short] = config("camp.enrollment_gates.{$key}"); @endphp
                            <div class="er-gate">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="min-w-0">
                                        <div class="er-sub text-uppercase" style="letter-spacing: .05em;">{{ $short }}</div>
                                        <div class="fw-semibold">{{ $label }}</div>
                                    </div>
                                    <span class="er-chip er-chip-{{ $gateTone($gate) }}">{{ $gateText($gate) }}</span>
                                </div>
                                @if($gate && $gate->status !== 'pending')
                                    <div class="er-sub mt-1">{{ optional($gate->approver)->name }} · {{ optional($gate->approved_at)->format('d M Y') }}</div>
                                    @if($gate->remarks)<div class="er-sub fst-italic">“{{ $gate->remarks }}”</div>@endif
                                @elseif($gate)
                                    <div class="er-sub mt-1">{{ $blockers[$key] ?? 'Ready to approve' }}</div>
                                    @if($gateLinks[$key])<a href="{{ $gateLinks[$key] }}" class="small fw-medium">Open queue <i class="ti ti-arrow-right"></i></a>@endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-md-6 col-xxl-12">
                    <div class="er-panel">
                        <div class="er-panel-head">
                            <div class="er-panel-title"><span class="er-title-icon er-title-icon-all"><i class="ti ti-user"></i></span> Student details</div>
                            @can('students.view')<a href="{{ route('admin.students.edit', $student->id) }}" class="small fw-medium">View profile</a>@endcan
                        </div>
                        <dl class="er-facts">
                            <dt>Date of birth</dt><dd>{{ optional($student->dob)->format('d M Y') ?? '—' }}</dd>
                            <dt>Gender</dt><dd>{{ ucfirst($student->gender ?? '—') }}</dd>
                            <dt>Email</dt><dd class="er-ellipsis" title="{{ $student->email }}">{{ $student->email ?: '—' }}</dd>
                            <dt>Phone</dt><dd>{{ $student->phone ?: '—' }}</dd>
                            @if($student->emergency_contact)<dt>Emergency</dt><dd>{{ $student->emergency_contact }}</dd>@endif
                            <dt>Parent</dt><dd>{{ $student->parent_name ?: '—' }}@if($student->parent_phone)<span class="er-sub justify-content-end">{{ $student->parent_phone }}</span>@endif</dd>
                            <dt>Address</dt><dd>{{ collect([$student->city, optional($student->state)->name, $student->pincode])->filter()->implode(', ') ?: '—' }}</dd>
                            <dt>Onboarding deadline</dt><dd>{{ $student->formatted_onboarding_deadline ?: '—' }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="col-md-6 col-xxl-12">
                    <div class="er-panel">
                        <div class="er-panel-head">
                            <div class="er-panel-title"><span class="er-title-icon er-title-icon-warn"><i class="ti ti-cash"></i></span> Fees</div>
                            @if(auth()->user()->can('fees.manage') || auth()->user()->can('payments.collect') || auth()->user()->can('payments.verify'))
                                <a href="{{ route('admin.students.fees', $student->id) }}" class="small fw-medium">Fees &amp; payments</a>
                            @endif
                        </div>
                        @php $paidPct = $fees['total'] > 0 ? min(100, round($fees['paid'] / $fees['total'] * 100)) : 0; @endphp
                        <div class="p-3">
                            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Paid</span><span class="er-mono fw-semibold">{{ money_inr($fees['paid'], false) }} / {{ money_inr($fees['total'], false) }}</span></div>
                            <div class="er-bar"><span style="width: {{ $paidPct }}%;"></span></div>
                            <div class="d-flex justify-content-between small mt-2">
                                <span class="text-muted">Outstanding</span>
                                <span class="er-mono fw-semibold {{ $fees['outstanding'] > 0 ? 'text-danger' : 'text-success' }}">{{ money_inr($fees['outstanding'], false) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Reprint modal --}}
    <div class="modal fade" id="reprintModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Reprint ID card</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="small text-muted">The new card must be printed and signed by the Training Manager again.</p>
                    @include('livewire.admin.students.partials.input', ['name' => 'reprintReason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'col' => 12, 'placeholder' => 'e.g. Card lost'])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" wire:click="cardReprint">Start reprint</button>
                </div>
            </div>
        </div>
    </div>

    @include('livewire.admin.onboarding.partials.styles')
    @include('livewire.admin.onboarding.partials.enrollment-styles')
    <style>
        /* header: details on the left, ER number card always on the right */
        .er-detail .er-profile { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 16px 24px; }
        .er-detail .er-profile .er-number-card { justify-self: end; }
        .er-detail .er-hchips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .er-detail .er-hchip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; background: #fff; border: 1px solid #E5E7EB; font-size: 12.5px; color: #374151; white-space: nowrap; }
        .er-detail .er-hchip i { color: #9CA3AF; }
        .er-detail .er-hchip-period { background: #EDE9FE; border-color: #DDD6FE; color: #6D28D9; font-weight: 600; }
        .er-detail .er-hchip-period i { color: #7C3AED; }
        .er-detail .er-profile-photo { width: 72px; height: 72px; border-radius: 16px; object-fit: cover; flex-shrink: 0; border: 3px solid #fff; box-shadow: 0 4px 12px rgba(16, 24, 40, .12); }
        .er-detail .er-profile-initials { display: inline-flex; align-items: center; justify-content: center; background: #FEF0E7; color: var(--er-accent); font-weight: 700; font-size: 22px; }
        .er-detail .er-meta { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 6px; font-size: 13px; color: #4B5563; }
        .er-detail .er-meta span { display: inline-flex; align-items: center; gap: 4px; }
        .er-detail .er-number-card { display: flex; flex-direction: column; gap: 2px; padding: 14px 20px; border-radius: 14px; min-width: 230px; color: #fff; background: linear-gradient(135deg, #1F2937 0%, #111827 100%); box-shadow: 0 8px 20px rgba(17, 24, 39, .25); }
        .er-detail .er-number-label { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #F9A26C; }
        .er-detail .er-number-value { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 24px; font-weight: 700; letter-spacing: .02em; }
        .er-detail .er-number-none { font-family: inherit; font-size: 18px; font-weight: 600; }
        .er-detail .er-number-sub { font-size: 12px; color: #9CA3AF; }

        .er-detail .er-journey { list-style: none; display: grid; grid-template-columns: repeat(5, 1fr); margin: 0; padding: 18px 16px 16px; }
        .er-detail .er-journey li { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 2px; padding: 0 4px; }
        .er-detail .er-journey li::before { content: ''; position: absolute; top: 15px; left: calc(-50% + 18px); right: calc(50% + 18px); height: 3px; border-radius: 2px; background: #E5E7EB; }
        .er-detail .er-journey li:first-child::before { display: none; }
        .er-detail .er-journey li.is-done::before, .er-detail .er-journey li.is-current::before { background: #22C55E; }
        .er-detail .er-journey-dot { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; background: #F3F4F6; color: #9CA3AF; border: 2px solid #E5E7EB; margin-bottom: 4px; }
        .er-detail .er-journey li.is-done .er-journey-dot { background: #22C55E; border-color: #22C55E; color: #fff; }
        .er-detail .er-journey li.is-current .er-journey-dot { background: #fff; border-color: var(--er-accent); color: var(--er-accent); box-shadow: 0 0 0 4px rgba(242, 101, 34, .15); }
        .er-detail .er-journey-title { font-weight: 600; font-size: 13.5px; color: var(--er-ink); }
        .er-detail .er-journey-sub { font-size: 11.5px; color: var(--er-muted); }
        .er-detail .er-journey li.is-current .er-journey-sub { color: var(--er-accent); font-weight: 600; }

        .er-detail .er-title-icon { width: 28px; height: 28px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; }
        .er-detail .er-title-icon-warn { background: #FEF3C7; color: #B45309; }
        .er-detail .er-title-icon-info { background: #E0F2FE; color: #0369A1; }
        .er-detail .er-title-icon-ok { background: #DCFCE7; color: #15803D; }
        .er-detail .er-title-icon-all { background: #EEF2FF; color: #4338CA; }

        .er-detail .er-timeline { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .er-detail .er-timeline li { position: relative; display: flex; gap: 12px; padding-bottom: 16px; }
        .er-detail .er-timeline li:last-child { padding-bottom: 0; }
        .er-detail .er-timeline li::before { content: ''; position: absolute; left: 11px; top: 26px; bottom: 2px; width: 2px; background: #E5E7EB; }
        .er-detail .er-timeline li:last-child::before { display: none; }
        .er-detail .er-timeline li.is-done::before { background: #22C55E; }
        .er-detail .er-timeline-dot { width: 24px; height: 24px; border-radius: 50%; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; background: #fff; border: 2px solid #D1D5DB; color: #fff; }
        .er-detail .er-timeline li.is-done .er-timeline-dot { background: #22C55E; border-color: #22C55E; }
        .er-detail .er-timeline li.is-current .er-timeline-dot { border-color: var(--er-accent); box-shadow: 0 0 0 4px rgba(242, 101, 34, .15); }
        .er-detail .er-timeline li:not(.is-done):not(.is-current) .fw-semibold { color: #9CA3AF; }

        .er-detail .er-card-stage { display: flex; justify-content: center; padding: 18px 12px; border-radius: 12px; background: radial-gradient(circle at 30% 20%, #FFF4EC 0%, #F3F4F6 70%); }
        .er-detail .er-card-stage .id-card-preview { box-shadow: 0 10px 24px rgba(17, 24, 39, .15); }
        .er-detail .er-card-facts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .er-detail .er-card-facts > div { display: flex; flex-direction: column; padding: 8px 10px; border-radius: 9px; background: #F9FAFB; border: 1px solid var(--er-soft); min-width: 0; }
        .er-detail .er-card-facts span { font-size: 11px; color: var(--er-muted); }
        .er-detail .er-card-facts strong { font-size: 13px; color: var(--er-ink); }
        .er-detail .er-note { display: flex; gap: 6px; align-items: flex-start; padding: 9px 12px; border-radius: 9px; font-size: 12.5px; background: #FBEFD9; color: #6A3805; }

        .er-detail .er-gate { padding: 12px 16px; border-bottom: 1px solid var(--er-soft); }
        .er-detail .er-gate:last-child { border-bottom: 0; }
        .er-detail .er-facts { display: grid; grid-template-columns: auto 1fr; margin: 0; padding: 4px 16px 10px; font-size: 13px; }
        .er-detail .er-facts dt, .er-detail .er-facts dd { padding: 7px 0; border-bottom: 1px dashed var(--er-soft); }
        .er-detail .er-facts dt { color: var(--er-muted); font-weight: 400; padding-right: 14px; white-space: nowrap; }
        .er-detail .er-facts dd { margin: 0; text-align: right; color: var(--er-ink); min-width: 0; }
        .er-detail .er-facts dt:last-of-type, .er-detail .er-facts dd:last-of-type { border-bottom: 0; }
        .er-detail .er-bar { height: 8px; border-radius: 999px; background: #F3F4F6; overflow: hidden; }
        .er-detail .er-bar span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #22C55E, #16A34A); }
        .er-detail .er-empty { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 56px 16px; text-align: center; }
        .er-detail .er-empty-icon { width: 64px; height: 64px; border-radius: 50%; background: #FFF4EC; color: var(--er-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 8px; }
        @media (max-width: 767.98px) {
            .er-detail .er-journey { grid-template-columns: 1fr; gap: 10px; }
            .er-detail .er-journey li { flex-direction: row; text-align: left; gap: 10px; }
            .er-detail .er-journey li::before { display: none; }
            .er-detail .er-profile { grid-template-columns: 1fr; }
            .er-detail .er-profile .er-number-card { width: 100%; justify-self: stretch; }
        }
    </style>
</div>

<script>
document.addEventListener('livewire:load', function () {
    // A print in another tab (ER form / ID card page) → refresh the counts and steps here
    if ('BroadcastChannel' in window) {
        new BroadcastChannel('camp-prints').onmessage = function (event) {
            const key = (event.data && event.data.key) || '';
            if (key === 'id-card:{{ $student->id }}' || key === 'er-form:{{ $student->id }}') {
                @this.call('$refresh');
            }
        };
    }

    window.addEventListener('close-reprint-modal', () => {
        const instance = bootstrap.Modal.getInstance(document.getElementById('reprintModal'));
        if (instance) {
            instance.hide();
        }
    });
});
</script>
