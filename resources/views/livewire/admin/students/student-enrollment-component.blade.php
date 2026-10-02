@php
    $gateLinks = [
        \App\Models\Admin\EnrollmentApproval::DOCUMENTS => auth()->user()->can('onboarding.verify_documents') ? route('admin.onboarding.documents', ['student' => $student->id]) : null,
        \App\Models\Admin\EnrollmentApproval::FEES => auth()->user()->can('payments.verify') ? route('admin.onboarding.payments', ['tab' => 'gate']) : null,
    ];
    $check = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7.5 L6 10 L11 4"></path></svg>';
@endphp
<div class="content">
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2 d-flex align-items-center gap-3">
            <span class="avatar avatar-lg rounded-circle bg-soft-success text-success fw-semibold d-flex align-items-center justify-content-center">{{ $student->initials }}</span>
            <div>
                <h2 class="mb-1 fw-semibold">{{ $student->full_name }}</h2>
                <span class="text-muted">{{ optional($student->course)->name }} · joined {{ $student->formatted_joining_date }}</span>
            </div>
        </div>
        {!! $student->status_html !!}
    </div>

    @include('livewire.admin.students.partials.student-nav', ['student' => $student, 'active' => 'enrollment'])

    {{-- Gates + ER number --}}
    <div class="row g-3 mb-3">
        @foreach($gates as $key => $gate)
            @php [$label, $short] = config("camp.enrollment_gates.{$key}"); @endphp
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-uppercase text-muted" style="letter-spacing: .06em;">{{ $short }}</span>
                            @php $status = optional($gate)->status ?? 'not_submitted'; @endphp
                            <span class="badge badge-soft-{{ ['approved' => 'success', 'rejected' => 'danger', 'pending' => 'info'][$status] ?? 'secondary' }}">
                                {{ ['approved' => 'Approved', 'rejected' => 'Rejected', 'pending' => 'Pending'][$status] ?? 'Not submitted' }}
                            </span>
                        </div>
                        <div class="fw-semibold">{{ $label }}</div>
                        @if($gate && $gate->status !== 'pending')
                            <div class="small text-muted">{{ optional($gate->approver)->name }} · {{ optional($gate->approved_at)->format('d M Y') }} @if($gate->remarks) · “{{ $gate->remarks }}” @endif</div>
                        @elseif($gate)
                            <div class="small text-muted">{{ $blockers[$key] ?? 'Ready to approve' }}</div>
                            @if($gateLinks[$key])<a href="{{ $gateLinks[$key] }}" class="small">Open queue</a>@endif
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-lg-4">
            <div class="gate-card-dark p-3 h-100 d-flex flex-column gap-1">
                <span class="label">ER number</span>
                @if($student->er_number)
                    <span class="amount fs-3 fw-medium">{{ $student->er_number }}</span>
                    <span class="small" style="color: #C9D1CB;">Generated {{ optional(optional($form)->generated_at)->format('d M Y') }} after both gates</span>
                @else
                    <span class="fs-5 fw-medium">Not issued yet</span>
                    <span class="small" style="color: #C9D1CB;">Issued automatically when Gate 1 and Gate 2 are approved</span>
                @endif
            </div>
        </div>
    </div>

    @if($form || $card)
        <div class="row g-3">
            {{-- ER request form --}}
            <div class="col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-semibold mb-0">ER request form</h5>
                            @php
                                $formBadge = match ($form->status) {
                                    'archived' => ['Archived', 'success'],
                                    'signed' => ['Signed — to archive', 'info'],
                                    'printed' => ['Awaiting TM signature', 'warning'],
                                    default => ['To print', 'secondary'],
                                };
                            @endphp
                            <span class="badge badge-soft-{{ $formBadge[1] }}">{{ $formBadge[0] }}</span>
                        </div>

                        @php
                            $steps = [
                                ['Form generated', optional($form->generated_at)->format('d M, H:i'), true],
                                ['Printed', $form->printed_at ? $form->printed_at->format('d M, H:i') : 'Print the form for the TM to sign', (bool) $form->printed_at],
                                ['Physically signed by Training Manager', $form->tm_signed_at ? optional($form->signer)->name . ' · ' . $form->tm_signed_at->format('d M, H:i') : 'Mark once the TM has signed the printed form', $form->tm_signature_status === 'physically_signed'],
                                ['Archived in physical file', $form->archived_at ? optional($form->archiver)->name . ' · ' . $form->archived_at->format('d M, H:i') : 'Confirms the signed form is filed', (bool) $form->archived_at],
                            ];
                            $currentFound = false;
                        @endphp
                        <ol class="list-unstyled mb-0 d-flex flex-column gap-3">
                            @foreach($steps as [$title, $sub, $done])
                                @php $isCurrent = !$done && !$currentFound; if ($isCurrent) { $currentFound = true; } @endphp
                                <li class="d-flex gap-3">
                                    <span class="step-dot {{ $done ? 'step-done' : ($isCurrent ? 'step-current' : 'step-todo') }}">{!! $done ? $check : '' !!}</span>
                                    <span class="d-flex flex-column {{ !$done && !$isCurrent ? 'text-muted' : '' }}">
                                        <span class="fw-semibold">{{ $title }}</span><span class="small text-muted">{{ $sub }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ol>

                        <div class="d-flex gap-2 flex-wrap mt-auto">
                            <a href="{{ route('admin.students.er-form', $student->id) }}" target="_blank" class="btn btn-outline-secondary"><i class="ti ti-printer me-1"></i> Open form</a>
                            @if($canManage)
                                @if(!$form->printed_at)
                                    <button type="button" class="btn btn-primary" wire:click="formPrinted">Mark printed</button>
                                @elseif($form->tm_signature_status !== 'physically_signed')
                                    <button type="button" class="btn btn-success" wire:click="formSigned">Mark TM signed</button>
                                @elseif(!$form->archived_at)
                                    <button type="button" class="btn btn-success" wire:click="formArchived">Confirm archived</button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ID card --}}
            <div class="col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-semibold mb-0">Student ID card</h5>
                            @php
                                $cardBadge = $card->tm_signature_status === 'physically_signed'
                                    ? ['Issued', 'success']
                                    : ($card->status === 'reprinted' ? ['Reprint — to sign', 'warning'] : ['Pending', 'secondary']);
                            @endphp
                            <span class="badge badge-soft-{{ $cardBadge[1] }}">{{ $cardBadge[0] }}</span>
                        </div>

                        @include('livewire.admin.students.partials.id-card', ['student' => $student, 'card' => $card, 'photo' => $photo])

                        <p class="small rounded p-2 mb-0" style="background: #FBEFD9; color: #6A3805;">
                            The card is valid only after the Training Manager signs it by hand.
                            @if($card->print_count) Printed {{ $card->print_count }} time(s). @endif
                            @if($card->issue_date) Issued {{ $card->issue_date->format('d M Y') }}{{ $card->signer ? ' · ' . $card->signer->name : '' }}. @endif
                        </p>

                        <div class="d-flex gap-2 flex-wrap mt-auto">
                            <a href="{{ route('admin.students.id-card', $student->id) }}" target="_blank" class="btn btn-outline-secondary"
                               @if($canManage && $card->tm_signature_status !== 'physically_signed') wire:click="cardPrinted" @endif>
                                <i class="ti ti-printer me-1"></i> Print card
                            </a>
                            @if($canManage)
                                @if($card->tm_signature_status !== 'physically_signed')
                                    <button type="button" class="btn btn-success" wire:click="cardIssued" @if(!$card->print_count) disabled title="Print the card first" @endif>
                                        Mark signed &amp; issued
                                    </button>
                                @else
                                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reprintModal">Reprint</button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card shadow-sm border-0">
            <div class="card-body text-center text-muted py-5">
                <i class="ti ti-id fs-1 d-block mb-2"></i>
                The ER request form and ID card are prepared once the ER number is issued.
            </div>
        </div>
    @endif

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
</div>

<script>
document.addEventListener('livewire:load', function () {
    window.addEventListener('close-reprint-modal', () => {
        const instance = bootstrap.Modal.getInstance(document.getElementById('reprintModal'));
        if (instance) {
            instance.hide();
        }
    });
});
</script>
