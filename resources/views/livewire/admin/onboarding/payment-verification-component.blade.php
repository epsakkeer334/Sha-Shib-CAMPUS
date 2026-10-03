@php
    $cards = [
        'all' => ['All payments', number_format($counts['all']), 'ti ti-receipt', 'all', 'Every recorded payment'],
        'pending' => ['To verify', number_format($counts['pending']), 'ti ti-hourglass-high', 'warn', money_inr($counts['pendingAmount'], false) . ' waiting'],
        'approved' => ['Approved', number_format($counts['approved']), 'ti ti-circle-check', 'ok', money_inr($counts['approvedAmount'], false) . ' received'],
        'rejected' => ['Rejected', number_format($counts['rejected']), 'ti ti-circle-x', 'bad', 'Not confirmed'],
        'gate' => ['Fee gate', number_format($counts['gate']), 'ti ti-shield-check', 'gate', 'Waiting for Gate 2'],
    ];
    $statusChip = fn ($p) => match ($p->status) {
        'success' => ['pv-chip-ok', 'Approved', 'ti ti-circle-check', 'ok'],
        'failed' => ['pv-chip-bad', 'Rejected', 'ti ti-circle-x', 'bad'],
        'refunded' => ['pv-chip-muted', 'Refunded', 'ti ti-arrow-back-up', 'muted'],
        default => ['pv-chip-warn', 'To verify', 'ti ti-hourglass-high', 'warn'],
    };
    // Fee gate (Gate 2) state for a student: [chip class, label, icon]
    $gateChip = function ($student) {
        $gate = $student ? $student->gate(\App\Models\Admin\EnrollmentApproval::FEES) : null;
        $docsApproved = $student && optional($student->gate(\App\Models\Admin\EnrollmentApproval::DOCUMENTS))->status === 'approved';
        return match (true) {
            !$gate => ['pv-chip-muted', 'Not submitted', 'ti ti-circle-dashed'],
            $gate->status === 'approved' => ['pv-chip-ok', 'Approved', 'ti ti-shield-check'],
            $gate->status === 'rejected' => ['pv-chip-bad', 'Rejected', 'ti ti-shield-x'],
            !$docsApproved => ['pv-chip-muted', 'Awaiting docs', 'ti ti-file-search'],
            default => ['pv-chip-warn', 'Waiting', 'ti ti-hourglass-high'],
        };
    };
@endphp
<div class="content pay-verify">
    {{-- Header --}}
    <div class="pv-hero mb-3">
        <div class="min-w-0">
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Student Onboarding</li>
                    <li class="breadcrumb-item active">Gate 2</li>
                </ol>
            </nav>
            <h2 class="mb-1 fw-bold">Payment verification</h2>
            <div class="text-muted small">Confirm student payments, issue receipts and clear the fee gate.</div>
        </div>
        @if($counts['pending'])
            <button type="button" class="pv-hero-cta" wire:click="$set('tab', 'pending')">
                <span class="pv-pulse"></span>
                <span><strong>{{ $counts['pending'] }}</strong> {{ \Illuminate\Support\Str::plural('payment', $counts['pending']) }} to verify</span>
                <i class="ti ti-arrow-right"></i>
            </button>
        @else
            <span class="pv-hero-done"><i class="ti ti-circle-check"></i> All payments verified</span>
        @endif
    </div>

    {{-- Status cards --}}
    <div class="pv-cards mb-3">
        @foreach($cards as $key => [$label, $count, $icon, $tone, $hint])
            <button type="button" wire:click="$set('tab', '{{ $key }}')" class="pv-card pv-card-{{ $tone }} {{ $tab === $key ? 'is-active' : '' }}"
                    aria-pressed="{{ $tab === $key ? 'true' : 'false' }}">
                <span class="pv-card-top">
                    <span class="pv-card-label">{{ $label }}</span>
                    <span class="pv-card-icon"><i class="{{ $icon }}"></i></span>
                </span>
                <span class="pv-card-count">{{ $count }}</span>
                <span class="pv-card-hint text-truncate">{{ $hint }}</span>
            </button>
        @endforeach
    </div>

    @if($tab === 'gate')
        {{-- Gate 2 · Fee clearance (waiting first, approved kept) --}}
        <div class="pv-panel">
            <div class="pv-panel-head">
                <div class="min-w-0">
                    <div class="pv-panel-title">Gate 2 · Fee clearance <span class="pv-count">{{ number_format($gateStudents->total()) }}</span></div>
                    <div class="small text-muted">Students with approved documents. Those waiting for Accounts are highlighted and shown first.</div>
                </div>
                <div class="pv-search">
                    <i class="ti ti-search"></i>
                    <input type="search" class="form-control form-control-sm" placeholder="Student name or ER number" wire:model.debounce.400ms="search" aria-label="Search students">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 pv-table">
                    <thead>
                        <tr><th>Student</th><th class="text-end">Outstanding</th><th>Fees</th><th>Gate 2</th><th class="text-end">Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse($gateStudents as $item)
                            @php
                                $feeGate = $item->gate(\App\Models\Admin\EnrollmentApproval::FEES);
                                $waiting = optional($feeGate)->status === 'pending';
                                $blocker = $waiting ? $onboarding->gateBlocker($item, \App\Models\Admin\EnrollmentApproval::FEES) : null;
                                $outstanding = $item->outstandingAmount();
                                $pendingPayments = $item->payments->where('status', 'pending_verification');
                            @endphp
                            <tr class="{{ $waiting ? 'pv-row-waiting' : '' }}" wire:key="gate-{{ $item->id }}">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="pv-avatar pv-avatar-{{ $waiting ? 'warn' : 'ok' }}">{{ $item->initials }}</span>
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.students.fees', $item->id) }}" class="pv-name pv-ellipsis">{{ $item->full_name }}</a>
                                            <div class="pv-sub">
                                                @if(optional($item->course)->code)<span class="pv-code">{{ $item->course->code }}</span>@endif
                                                <span class="pv-ellipsis" title="{{ $item->er_number }}"><i class="ti ti-phone"></i> {{ $item->phone ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end"><span class="pv-amount {{ $outstanding > 0 ? 'text-danger' : 'text-success' }}">{{ money_inr($outstanding, false) }}</span></td>
                                <td class="small">
                                    @if($item->dues->isEmpty()) <span class="text-danger"><i class="ti ti-alert-circle"></i> No fees set up</span>
                                    @elseif($pendingPayments->isNotEmpty()) <span class="pv-text-warn"><i class="ti ti-hourglass-high"></i> {{ money_inr($pendingPayments->sum('amount'), false) }} to verify</span>
                                    @elseif($outstanding > 0) <span class="text-muted">Not fully paid</span>
                                    @else <span class="text-success"><i class="ti ti-circle-check"></i> All cleared</span> @endif
                                </td>
                                <td>
                                    @if($waiting)
                                        <span class="pv-chip pv-chip-warn"><i class="ti ti-hourglass-high"></i> Waiting</span>
                                    @else
                                        <span class="pv-chip pv-chip-ok"><i class="ti ti-circle-check"></i> Approved</span>
                                        <div class="pv-sub mt-1">{{ optional(optional($feeGate)->approver)->name }} · {{ optional(optional($feeGate)->approved_at)->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($waiting)
                                        <button type="button" class="btn btn-success btn-sm" wire:click="approveGate({{ $item->id }})" wire:loading.attr="disabled"
                                                @if($blocker) disabled title="{{ $blocker }}" @endif>
                                            <i class="ti ti-check me-1"></i> Approve gate
                                        </button>
                                        @if($blocker)<div class="pv-sub pv-text-warn mt-1">{{ $blocker }}</div>@endif
                                    @else
                                        <a href="{{ route('admin.students.fees', $item->id) }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-cash me-1"></i> View fees</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="pv-empty-row"><i class="ti ti-shield-check"></i><div>No students have passed the documents gate yet.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($gateStudents->hasPages())<div class="pv-panel-foot">{{ $gateStudents->links() }}</div>@endif
        </div>
    @else
        <div class="row g-3">
            {{-- Payments --}}
            <div class="col-xl-8">
                <div class="pv-panel">
                    <div class="pv-panel-head">
                        <div class="pv-panel-title">{{ $cards[$tab][0] ?? 'Payments' }} <span class="pv-count">{{ number_format($payments->total()) }}</span></div>
                        <div class="d-flex gap-2 flex-wrap">
                            <div class="pv-search">
                                <i class="ti ti-search"></i>
                                <input type="search" class="form-control form-control-sm" placeholder="Student, ER no., UTR or receipt" wire:model.debounce.400ms="search" aria-label="Search payments">
                            </div>
                            <select class="form-select form-select-sm pv-method-select" wire:model="method" aria-label="Payment method">
                                <option value="">All methods</option>
                                @foreach($methods as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            </select>
                            @if($search || $method)
                                <button type="button" class="btn btn-sm btn-light" wire:click="$set('search', ''); $set('method', '')" title="Clear filters"><i class="ti ti-x"></i></button>
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 pv-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Payment</th>
                                    <th class="text-end">Amount</th>
                                    <th>Reference</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Fee gate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payments as $payment)
                                    @php
                                        [$chipClass, $chipText, $chipIcon, $tone] = $statusChip($payment);
                                        $isWaiting = $payment->status === 'pending_verification';
                                        $ref = $payment->receipt_number ?: $payment->transaction_reference;
                                    @endphp
                                    <tr wire:click="select({{ $payment->id }})" wire:key="pay-{{ $payment->id }}"
                                        class="pv-row {{ $isWaiting ? 'pv-row-waiting' : '' }} {{ optional($selected)->id === $payment->id ? 'is-selected' : '' }}">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="pv-avatar pv-avatar-{{ $tone }}">{{ optional($payment->student)->initials }}</span>
                                                <div class="min-w-0">
                                                    <div class="pv-name pv-ellipsis">{{ optional($payment->student)->full_name }}</div>
                                                    <div class="pv-sub">
                                                        @if(optional(optional($payment->student)->course)->code)<span class="pv-code">{{ $payment->student->course->code }}</span>@endif
                                                        <span class="pv-ellipsis" title="{{ optional($payment->student)->er_number }}"><i class="ti ti-phone"></i> {{ optional($payment->student)->phone ?: '—' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="pv-ellipsis small fw-medium" style="max-width: 160px;">{{ optional($payment->due)->fee_head ?? '—' }}</div>
                                            <span class="pv-method"><i class="ti ti-{{ \Illuminate\Support\Str::contains(strtolower(optional($payment->gateway)->name), 'cash') ? 'cash' : 'credit-card' }}"></i> {{ optional($payment->gateway)->name }}</span>
                                        </td>
                                        <td class="text-end"><span class="pv-amount">{{ money_inr($payment->amount, false) }}</span></td>
                                        <td>
                                            {{-- truncate inside a wrapper: max-width on a <td> is ignored and long UTRs stretched the table --}}
                                            <div class="pv-ref" title="{{ $ref }}">
                                                @if($payment->receipt_number)<i class="ti ti-receipt-2 text-success"></i>@endif
                                                <span class="pv-ellipsis">{{ $ref ?: '—' }}</span>
                                            </div>
                                        </td>
                                        <td class="text-nowrap">
                                            <div class="small">{{ $payment->created_at->format('d M Y') }}</div>
                                            <div class="pv-sub">{{ $payment->created_at->diffForHumans() }}</div>
                                        </td>
                                        <td><span class="pv-chip {{ $chipClass }}"><i class="{{ $chipIcon }}"></i> {{ $chipText }}</span></td>
                                        @php [$gClass, $gText, $gIcon] = $gateChip($payment->student); @endphp
                                        <td><span class="pv-chip {{ $gClass }}"><i class="{{ $gIcon }}"></i> {{ $gText }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="pv-empty-row"><i class="ti ti-receipt-off"></i><div>No payments here.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($payments->hasPages())<div class="pv-panel-foot">{{ $payments->links() }}</div>@endif
                </div>
            </div>

            {{-- Detail panel --}}
            <div class="col-xl-4">
                <div class="pv-panel pv-detail">
                    @if(!$selected)
                        <div class="pv-empty">
                            <span class="pv-empty-icon"><i class="ti ti-hand-click"></i></span>
                            <div class="fw-semibold">Select a payment</div>
                            <div class="small text-muted">Click a row to see the proof and verify it.</div>
                        </div>
                    @else
                        @php
                            $due = $selected->due;
                            $balance = $due ? $due->balance : null;
                            [$chipClass, $chipText, $chipIcon, $tone] = $statusChip($selected);
                        @endphp
                        <div class="pv-detail-banner pv-banner-{{ $tone }}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="pv-banner-label">Amount</span>
                                <span class="pv-chip {{ $chipClass }}"><i class="{{ $chipIcon }}"></i> {{ $chipText }}</span>
                            </div>
                            <div class="pv-banner-amount">{{ money_inr($selected->amount) }}</div>
                            <div class="pv-banner-sub">{{ optional($due)->fee_head ?? 'Payment' }} · {{ optional($selected->gateway)->name }}</div>
                        </div>

                        <div class="pv-detail-body">
                            <a href="{{ route('admin.students.fees', $selected->student_id) }}" class="pv-student-link">
                                <span class="pv-avatar pv-avatar-{{ $tone }}">{{ optional($selected->student)->initials }}</span>
                                <span class="min-w-0 flex-grow-1">
                                    <span class="pv-name pv-ellipsis d-block">{{ optional($selected->student)->full_name }}</span>
                                    <span class="pv-sub"><span><i class="ti ti-phone"></i> {{ optional($selected->student)->phone ?: '—' }}</span>@if(optional($selected->student)->er_number)<span>· {{ $selected->student->er_number }}</span>@endif</span>
                                </span>
                                <i class="ti ti-chevron-right text-muted"></i>
                            </a>

                            @if($selected->proof_file_path)
                                <a href="{{ route('admin.students.payments.proof', $selected->id) }}" target="_blank" class="pv-proof" title="Open payment proof">
                                    @if($selected->proof_is_image)
                                        <img src="{{ route('admin.students.payments.proof', $selected->id) }}" alt="Payment proof">
                                    @else
                                        <span class="text-center"><i class="ti ti-file-type-pdf d-block" style="font-size: 34px; color: #DC2626;"></i><span class="small">Payment proof (PDF)</span></span>
                                    @endif
                                    <span class="pv-proof-open"><i class="ti ti-external-link"></i> Open</span>
                                </a>
                            @endif

                            <dl class="pv-facts">
                                @if($due)<dt>Due balance</dt><dd class="pv-amount">{{ money_inr($balance) }}</dd>@endif
                                <dt>Reference</dt><dd class="pv-mono">{{ $selected->transaction_reference ?: '—' }}</dd>
                                @if($selected->payer_upi_id)<dt>Payer UPI</dt><dd>{{ $selected->payer_upi_id }}</dd>@endif
                                <dt>Paid on</dt><dd>{{ optional($selected->paid_at)->format('d M Y') ?? '—' }}</dd>
                                <dt>Recorded</dt><dd>{{ $selected->created_at->format('d M Y, h:i A') }}<span class="pv-sub d-block">{{ optional($selected->recorder)->name }}</span></dd>
                                @if($selected->receipt_number)<dt>Receipt</dt><dd class="pv-mono text-success">{{ $selected->receipt_number }}</dd>@endif
                                @if($selected->verifier)<dt>Decided by</dt><dd>{{ $selected->verifier->name }}<span class="pv-sub d-block">{{ optional($selected->verified_at)->format('d M Y, H:i') }}</span></dd>@endif
                                @if($selected->rejection_reason)<dt>Reason</dt><dd class="text-danger">{{ $selected->rejection_reason }}</dd>@endif
                                @if($selected->remarks)<dt>Remarks</dt><dd>{{ $selected->remarks }}</dd>@endif
                            </dl>

                            @if($selected->status === 'pending_verification')
                                @if($due)
                                    @if(abs((float) $selected->amount - $balance) < 0.01)
                                        <div class="pv-note pv-note-ok"><i class="ti ti-circle-check"></i> Amount matches the balance on this fee.</div>
                                    @elseif((float) $selected->amount < $balance)
                                        <div class="pv-note pv-note-warn"><i class="ti ti-info-circle"></i> Part payment — {{ money_inr($balance - $selected->amount) }} will remain.</div>
                                    @else
                                        <div class="pv-note pv-note-bad"><i class="ti ti-alert-triangle"></i> Amount is more than the fee balance.</div>
                                    @endif
                                @endif

                                <label class="pv-label mt-3" for="payReason">Reason <span class="text-muted fw-normal">(required to reject)</span></label>
                                <textarea id="payReason" class="form-control form-control-sm @error('reason') is-invalid @enderror" rows="2" wire:model.defer="reason"
                                          placeholder="e.g. UTR not found in bank statement"></textarea>
                                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="pv-actions">
                                    <button type="button" class="btn btn-outline-danger" wire:click="reject" wire:loading.attr="disabled"><i class="ti ti-x me-1"></i> Reject</button>
                                    <button type="button" class="btn btn-success" wire:click="approve" wire:loading.attr="disabled"><i class="ti ti-check me-1"></i> Approve &amp; issue receipt</button>
                                </div>
                                <p class="pv-sub mt-2 mb-0">Approval issues a receipt number, updates the fee and notifies the student. Both actions are logged.</p>
                            @elseif($selected->status === 'success')
                                <a href="{{ route('admin.students.payments.receipt', $selected->id) }}" target="_blank" class="btn btn-outline-success w-100 mt-2">
                                    <i class="ti ti-printer me-1"></i> Print receipt
                                </a>
                            @endif

                            {{-- Gate 2 for this student: approve here once the fees are cleared --}}
                            @php
                                $student = $selected->student;
                                $feeGate = $student ? $student->gate(\App\Models\Admin\EnrollmentApproval::FEES) : null;
                                [$gClass, $gText, $gIcon] = $gateChip($student);
                                $gateWaiting = optional($feeGate)->status === 'pending';
                                $gateBlocker = $gateWaiting ? $onboarding->gateBlocker($student, \App\Models\Admin\EnrollmentApproval::FEES) : null;
                                $outstanding = $student ? $student->outstandingAmount() : 0;
                            @endphp
                            <div class="pv-gate-box pv-gate-{{ $gateWaiting && !$gateBlocker ? 'ready' : 'idle' }}">
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div class="fw-semibold"><i class="ti ti-shield-check me-1"></i> Fee gate · Gate 2</div>
                                    <span class="pv-chip {{ $gClass }}"><i class="{{ $gIcon }}"></i> {{ $gText }}</span>
                                </div>
                                <div class="pv-sub mt-1">Outstanding: <span class="pv-amount {{ $outstanding > 0 ? 'text-danger' : 'text-success' }}">{{ money_inr($outstanding, false) }}</span></div>
                                @if(optional($feeGate)->status === 'approved')
                                    <div class="pv-sub mt-1">Approved by {{ optional($feeGate->approver)->name ?? '—' }} · {{ optional($feeGate->approved_at)->format('d M Y, H:i') }}</div>
                                @elseif($gateWaiting)
                                    @if($gateBlocker)
                                        <div class="pv-sub pv-text-warn mt-1"><i class="ti ti-info-circle"></i> {{ $gateBlocker }}</div>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-success w-100 mt-2" wire:click="approveGate({{ $student->id }})" wire:loading.attr="disabled"
                                            @if($gateBlocker) disabled title="{{ $gateBlocker }}" @endif>
                                        <i class="ti ti-shield-check me-1"></i> Approve fee gate
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <style>
        .pay-verify { --pv-border: #E5E7EB; --pv-soft: #F1F2F4; --pv-ink: #111827; --pv-muted: #6B7280; --pv-accent: #F26522; }
        .pay-verify .min-w-0 { min-width: 0; }
        .pay-verify .pv-ellipsis { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
        .pay-verify .pv-sub { font-size: 12px; color: var(--pv-muted); display: flex; align-items: center; gap: 6px; min-width: 0; }
        .pay-verify .pv-text-warn { color: #B45309; }

        /* hero */
        .pay-verify .pv-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--pv-border);
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .pay-verify .pv-hero h2 { font-size: 22px; color: var(--pv-ink); }
        .pay-verify .pv-hero-cta { display: inline-flex; align-items: center; gap: 10px; padding: 10px 16px; border-radius: 999px; border: 0; background: var(--pv-accent); color: #fff; font-size: 14px; box-shadow: 0 6px 16px rgba(242, 101, 34, .3); transition: transform .15s; }
        .pay-verify .pv-hero-cta:hover { transform: translateY(-1px); }
        .pay-verify .pv-pulse { width: 9px; height: 9px; border-radius: 50%; background: #fff; animation: pvPulse 1.6s infinite; }
        @keyframes pvPulse { 0% { box-shadow: 0 0 0 0 rgba(255, 255, 255, .8); } 100% { box-shadow: 0 0 0 9px rgba(255, 255, 255, 0); } }
        .pay-verify .pv-hero-done { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 999px; background: #DCFCE7; color: #15803D; font-weight: 600; font-size: 13.5px; }

        /* status cards */
        .pay-verify .pv-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .pay-verify .pv-card { --tone: #4338CA; --tone-soft: #EEF2FF; position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 14px 16px 16px; background: #fff; border: 1px solid var(--pv-border); border-radius: 14px; text-align: left; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); transition: box-shadow .15s, transform .15s, border-color .15s; }
        .pay-verify .pv-card::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--tone); opacity: 0; transition: opacity .15s; }
        .pay-verify .pv-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 24, 40, .08); }
        .pay-verify .pv-card.is-active { border-color: var(--tone); box-shadow: 0 8px 20px rgba(16, 24, 40, .08); background: linear-gradient(180deg, var(--tone-soft) 0%, #fff 70%); }
        .pay-verify .pv-card.is-active::after { opacity: 1; }
        .pay-verify .pv-card-top { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .pay-verify .pv-card-label { font-size: 13px; font-weight: 600; color: #374151; }
        .pay-verify .pv-card-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: var(--tone-soft); color: var(--tone); }
        .pay-verify .pv-card-count { font-size: 26px; font-weight: 700; line-height: 1.15; color: var(--pv-ink); }
        .pay-verify .pv-card-hint { font-size: 12px; color: var(--pv-muted); max-width: 100%; }
        .pay-verify .pv-card-all { --tone: #4338CA; --tone-soft: #EEF2FF; }
        .pay-verify .pv-card-warn { --tone: #D97706; --tone-soft: #FEF3C7; }
        .pay-verify .pv-card-ok { --tone: #16A34A; --tone-soft: #DCFCE7; }
        .pay-verify .pv-card-bad { --tone: #DC2626; --tone-soft: #FEE2E2; }
        .pay-verify .pv-card-gate { --tone: #0284C7; --tone-soft: #E0F2FE; }

        /* panels */
        .pay-verify .pv-panel { background: #fff; border: 1px solid var(--pv-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .pay-verify .pv-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 16px; border-bottom: 1px solid var(--pv-soft); }
        .pay-verify .pv-panel-title { font-weight: 600; font-size: 15px; color: var(--pv-ink); display: flex; align-items: center; gap: 8px; }
        .pay-verify .pv-panel-foot { padding: 12px 16px; border-top: 1px solid var(--pv-soft); }
        .pay-verify .pv-panel-foot nav p { margin: 0; }
        .pay-verify .pv-panel-foot .pagination { margin: 0; }
        .pay-verify .pv-count { min-width: 26px; height: 22px; padding: 0 8px; border-radius: 999px; background: #F3F4F6; color: #374151; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; }
        .pay-verify .pv-search { position: relative; width: 260px; max-width: 100%; }
        .pay-verify .pv-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .pay-verify .pv-search input { padding-left: 32px; border-radius: 8px; }
        .pay-verify .pv-method-select { width: 150px; border-radius: 8px; }
        .pay-verify .pv-label { font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px; display: block; }

        /* table */
        .pay-verify .pv-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--pv-muted); border-bottom: 1px solid var(--pv-border); padding: 10px 14px; white-space: nowrap; }
        .pay-verify .pv-table td { padding: 12px 14px; border-color: var(--pv-soft); font-size: 14px; vertical-align: middle; }
        .pay-verify .pv-table tbody tr:last-child td { border-bottom: 0; }
        .pay-verify .pv-row { cursor: pointer; transition: background .12s; }
        .pay-verify .pv-row:hover td { background: #FAFAFB; }
        .pay-verify .pv-row-waiting td, .pay-verify .pv-row-waiting:hover td { background: #FFFBEB; }
        .pay-verify .pv-row-waiting td:first-child { box-shadow: inset 3px 0 0 #F59E0B; }
        .pay-verify .pv-row-waiting .pv-name { font-weight: 700; }
        .pay-verify .pv-row.is-selected td { background: #FFF4EC !important; }
        .pay-verify .pv-row.is-selected td:first-child { box-shadow: inset 3px 0 0 var(--pv-accent); }
        .pay-verify .pv-avatar { width: 36px; height: 36px; border-radius: 50%; font-weight: 600; font-size: 12.5px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; background: #FEF0E7; color: var(--pv-accent); }
        .pay-verify .pv-avatar-warn { background: #FEF3C7; color: #B45309; }
        .pay-verify .pv-avatar-ok { background: #DCFCE7; color: #15803D; }
        .pay-verify .pv-avatar-bad { background: #FEE2E2; color: #DC2626; }
        .pay-verify .pv-avatar-muted { background: #F3F4F6; color: var(--pv-muted); }
        .pay-verify .pv-name { font-weight: 600; color: var(--pv-ink); max-width: 200px; }
        .pay-verify a.pv-name:hover { color: var(--pv-accent); }
        .pay-verify .pv-code { padding: 0 6px; border-radius: 5px; background: #EEF2FF; color: #4338CA; font-weight: 600; font-size: 11px; flex-shrink: 0; }
        .pay-verify .pv-method { display: inline-flex; align-items: center; gap: 4px; margin-top: 3px; padding: 1px 8px; border-radius: 6px; background: #F3F4F6; color: #374151; font-size: 11.5px; white-space: nowrap; }
        .pay-verify .pv-amount { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-weight: 600; white-space: nowrap; color: var(--pv-ink); }
        .pay-verify .pv-mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .pay-verify .pv-ref { display: flex; align-items: center; gap: 4px; width: 130px; max-width: 130px; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 12.5px; color: #374151; }
        .pay-verify .pv-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
        .pay-verify .pv-chip-ok { background: #DCFCE7; color: #15803D; }
        .pay-verify .pv-chip-warn { background: #FEF3C7; color: #B45309; }
        .pay-verify .pv-chip-bad { background: #FEE2E2; color: #DC2626; }
        .pay-verify .pv-chip-muted { background: #F3F4F6; color: var(--pv-muted); }
        .pay-verify .pv-empty-row { text-align: center; color: var(--pv-muted); padding: 48px 12px !important; }
        .pay-verify .pv-empty-row i { font-size: 36px; color: #D1D5DB; display: block; margin-bottom: 6px; }

        /* detail */
        .pay-verify .pv-detail { position: sticky; top: 80px; }
        .pay-verify .pv-empty { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 64px 16px; text-align: center; }
        .pay-verify .pv-empty-icon { width: 64px; height: 64px; border-radius: 50%; background: #FFF4EC; color: var(--pv-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 8px; }
        .pay-verify .pv-detail-banner { padding: 16px 18px; color: #fff; }
        .pay-verify .pv-banner-warn { background: linear-gradient(135deg, #F59E0B 0%, #F26522 100%); }
        .pay-verify .pv-banner-ok { background: linear-gradient(135deg, #22C55E 0%, #15803D 100%); }
        .pay-verify .pv-banner-bad { background: linear-gradient(135deg, #F87171 0%, #DC2626 100%); }
        .pay-verify .pv-banner-muted { background: linear-gradient(135deg, #9CA3AF 0%, #4B5563 100%); }
        .pay-verify .pv-detail-banner .pv-chip { background: rgba(255, 255, 255, .92); }
        .pay-verify .pv-banner-label { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; opacity: .9; }
        .pay-verify .pv-banner-amount { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 28px; font-weight: 700; line-height: 1.2; margin-top: 2px; }
        .pay-verify .pv-banner-sub { font-size: 13px; opacity: .92; }
        .pay-verify .pv-detail-body { padding: 16px 18px 18px; }
        .pay-verify .pv-student-link { display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid var(--pv-border); border-radius: 10px; margin-bottom: 14px; text-decoration: none; transition: border-color .15s; }
        .pay-verify .pv-student-link:hover { border-color: var(--pv-accent); }
        .pay-verify .pv-proof { position: relative; display: flex; align-items: center; justify-content: center; height: 200px; border-radius: 10px; background: repeating-conic-gradient(#F3F4F6 0 25%, #FAFAFA 0 50%) 0 0 / 16px 16px; border: 1px solid var(--pv-border); overflow: hidden; text-decoration: none; color: #374151; margin-bottom: 14px; }
        .pay-verify .pv-proof img { width: 100%; height: 100%; object-fit: contain; }
        .pay-verify .pv-proof-open { position: absolute; right: 8px; bottom: 8px; padding: 2px 9px; border-radius: 6px; background: rgba(17, 24, 39, .75); color: #fff; font-size: 11px; }
        .pay-verify .pv-facts { display: grid; grid-template-columns: auto 1fr; gap: 0; font-size: 13px; margin: 0 0 6px; }
        .pay-verify .pv-facts dt, .pay-verify .pv-facts dd { padding: 7px 0; border-bottom: 1px dashed var(--pv-soft); }
        .pay-verify .pv-facts dt { color: var(--pv-muted); font-weight: 400; padding-right: 14px; }
        .pay-verify .pv-facts dd { margin: 0; text-align: right; color: var(--pv-ink); word-break: break-word; }
        .pay-verify .pv-facts dd .pv-sub { justify-content: flex-end; }
        .pay-verify .pv-note { display: flex; align-items: center; gap: 6px; padding: 9px 12px; border-radius: 9px; font-size: 12.5px; margin-top: 8px; }
        .pay-verify .pv-note-ok { background: #DCFCE7; color: #166534; }
        .pay-verify .pv-note-warn { background: #FEF3C7; color: #92400E; }
        .pay-verify .pv-note-bad { background: #FEE2E2; color: #991B1B; }
        .pay-verify .pv-gate-box { margin-top: 16px; padding: 12px 14px; border-radius: 10px; border: 1px solid var(--pv-border); background: #F9FAFB; }
        .pay-verify .pv-gate-ready { border-color: #86EFAC; background: #F0FDF4; }
        .pay-verify .pv-sub i { font-size: 13px; }
        .pay-verify .pv-actions { display: grid; grid-template-columns: 1fr 1.6fr; gap: 8px; margin-top: 10px; }
        @media (max-width: 575.98px) { .pay-verify .pv-search, .pay-verify .pv-method-select { width: 100%; } .pay-verify .pv-actions { grid-template-columns: 1fr; } }
    </style>
</div>
