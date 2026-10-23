@php
    $user = auth()->user();
    $canAdvance = $user->can('payments.collect') || $user->can('fees.manage');
    $charged = $dues->where('status', '!=', 'waived')->sum(fn ($d) => $d->net_amount); // after scholarships
    $discountTotal = $dues->where('status', '!=', 'waived')->sum(fn ($d) => (float) $d->discount);
    $grandTotal = $charged + $upcomingTotal;
    $paidPct = $grandTotal > 0 ? min(100, round($paidTotal / $grandTotal * 100)) : 0;
    $duePct = $grandTotal > 0 ? min(100 - $paidPct, round($outstanding / $grandTotal * 100)) : 0;
    $overdue = $dues->filter(fn ($d) => !$d->isSettled() && $d->due_date->isPast());
    $waiting = $payments->where('status', 'pending_verification');
    // due status → [label, tone]
    $dueChip = function ($due) {
        if ($due->status === 'cleared') return ['Paid', 'green'];
        if ($due->status === 'waived') return ['Waived', 'slate'];
        if ($due->status === 'partial') return ['Part paid', 'amber'];
        return $due->due_date->isPast() ? ['Overdue', 'red'] : ['Pending', 'blue'];
    };
    $payTone = ['success' => ['green', 'ti ti-circle-check'], 'pending_verification' => ['amber', 'ti ti-clock'], 'failed' => ['red', 'ti ti-circle-x'], 'refunded' => ['slate', 'ti ti-arrow-back'], 'initiated' => ['slate', 'ti ti-loader']];
    $gatewayIcon = fn ($g) => $g->type === 'upi' ? 'ti ti-qrcode' : (['cash' => 'ti ti-cash', 'cheque' => 'ti ti-file-dollar', 'bank_transfer' => 'ti ti-building-bank'][$g->code] ?? 'ti ti-wallet');
@endphp
<div class="content sf-ui">
    {{-- Student header --}}
    <div class="sf-hero mb-3">
        <div class="d-flex align-items-center gap-3 min-w-0">
            @if($photo && $photo->is_image)
                <img src="{{ route('admin.students.documents.show', $photo->id) }}" class="sf-photo" alt="Photo of {{ $student->full_name }}">
            @else
                <span class="sf-photo sf-initials">{{ $student->initials }}</span>
            @endif
            <div class="min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h2 class="mb-0 fw-bold">{{ $student->full_name }}</h2>
                    {!! $student->status_html !!}
                </div>
                <nav>
                    <ol class="breadcrumb mb-1 sl-crumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.students') }}">Students</a></li>
                        <li class="breadcrumb-item active">Fees &amp; Payments</li>
                    </ol>
                </nav>
                <div class="sf-meta">
                    @if($student->course)<span><span class="sf-code">{{ $student->course->code }}</span> {{ $student->course->name }}</span>@endif
                    @if($student->institute)<span><i class="ti ti-building"></i> {{ $student->institute->name }}</span>@endif
                </div>
                <div class="sf-chips">
                    @if($student->er_number)<span class="sf-chip sf-chip-er"><i class="ti ti-id-badge-2"></i> {{ $student->er_number }}</span>@endif
                    @if($student->batch)<span class="sf-chip"><i class="ti ti-users-group"></i> {{ $student->batch->code }}</span>@endif
                    @if($student->current_period_label)<span class="sf-chip sf-chip-period"><i class="ti ti-calendar-time"></i> {{ $student->current_period_label }}</span>@endif
                    <span class="sf-chip"><i class="ti ti-phone"></i> {{ $student->phone ?: '—' }}</span>
                </div>
            </div>
        </div>
        <div class="sf-balance">
            <span class="sf-balance-label">Still to pay</span>
            <span class="sf-balance-value">{{ money_inr($outstanding, false) }}</span>
            @if($nextDue)
                <span class="sf-balance-sub">Next: {{ $nextDue->fee_head }} · due {{ $nextDue->due_date->format('d M Y') }}</span>
            @elseif($outstanding <= 0 && $dues->isNotEmpty())
                <span class="sf-balance-sub"><i class="ti ti-circle-check"></i> All charged fees are settled</span>
            @else
                <span class="sf-balance-sub">No fees charged yet</span>
            @endif
        </div>
    </div>

    @include('livewire.admin.students.partials.student-nav', ['student' => $student, 'active' => 'fees'])

    {{-- Summary strip --}}
    <div class="sf-summary mb-3">
        <div class="sf-sum-item">
            <span class="sf-sum-label">All fees</span>
            <span class="sf-sum-value">{{ money_inr($grandTotal, false) }}</span>
            <span class="sf-sum-hint">{{ $dues->count() + $upcoming->count() }} {{ \Illuminate\Support\Str::plural('fee', $dues->count() + $upcoming->count()) }}</span>
        </div>
        <div class="sf-sum-item">
            <span class="sf-sum-label"><i class="sf-dot sf-dot-green"></i> Paid</span>
            <span class="sf-sum-value text-success">{{ money_inr($paidTotal, false) }}</span>
            <span class="sf-sum-hint">{{ $paidPct }}% of all fees</span>
        </div>
        <div class="sf-sum-item">
            <span class="sf-sum-label"><i class="sf-dot sf-dot-amber"></i> Still to pay</span>
            <span class="sf-sum-value">{{ money_inr($outstanding, false) }}</span>
            <span class="sf-sum-hint {{ $overdue->isNotEmpty() ? 'text-danger fw-semibold' : '' }}">{{ $overdue->isNotEmpty() ? $overdue->count() . ' overdue' : 'Nothing overdue' }}</span>
        </div>
        <div class="sf-sum-item">
            <span class="sf-sum-label"><i class="sf-dot sf-dot-blue"></i> Upcoming</span>
            <span class="sf-sum-value">{{ money_inr($upcomingTotal, false) }}</span>
            <span class="sf-sum-hint">Not charged yet</span>
        </div>
        <div class="sf-progress" title="{{ $paidPct }}% paid">
            <span class="sf-progress-paid" style="width: {{ $paidPct }}%"></span>
            <span class="sf-progress-due" style="width: {{ $duePct }}%"></span>
        </div>
    </div>

    @if($waiting->isNotEmpty())
        <div class="sf-note mb-3">
            <i class="ti ti-clock"></i>
            <span>{{ $waiting->count() }} {{ \Illuminate\Support\Str::plural('payment', $waiting->count()) }} ({{ money_inr($waiting->sum('amount'), false) }}) waiting for Accounts verification.</span>
            @can('payments.verify')<a href="{{ route('admin.onboarding.payments') }}" class="ms-auto">Verify now <i class="ti ti-arrow-right"></i></a>@endcan
        </div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            {{-- Fees --}}
            <div class="sf-panel mb-3">
                <div class="sf-panel-head">
                    <div class="d-flex align-items-center gap-2">
                        <span class="sf-panel-icon"><i class="ti ti-receipt-2"></i></span>
                        <div>
                            <h5 class="mb-0">Fees</h5>
                            <small class="text-muted">Paid in order of due date. The next one is highlighted.</small>
                        </div>
                    </div>
                    @can('fees.manage')
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="generateDues" wire:loading.attr="disabled">
                                <i class="ti ti-list-check me-1"></i> Generate from fee structure
                            </button>
                            <button type="button" class="btn btn-sm btn-light border" onclick="window.dispatchEvent(new CustomEvent('open-fee-modal', {detail: {id: 'addDueModal'}}))">
                                <i class="ti ti-plus me-1"></i> Add fee
                            </button>
                        </div>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 sf-table">
                        <thead>
                            <tr><th>Fee</th><th>Due date</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th><th class="text-end"></th></tr>
                        </thead>
                        <tbody>
                            @php $lastPeriod = -1; @endphp
                            @forelse($dues as $due)
                                @php
                                    $pending = $due->payments->where('status', 'pending_verification')->sum('amount');
                                    [$chipLabel, $chipTone] = $dueChip($due);
                                    $isNext = $nextDue && $nextDue->id === $due->id;
                                @endphp
                                @if((int) $due->period_no !== $lastPeriod)
                                    @php $lastPeriod = (int) $due->period_no; $periodDues = $dues->filter(fn ($d) => (int) $d->period_no === $lastPeriod); @endphp
                                    <tr class="sf-group">
                                        <td colspan="4"><i class="ti ti-{{ $lastPeriod ? 'calendar-time' : 'receipt' }}"></i> {{ $lastPeriod ? $due->period_name : 'One-time fees' }}
                                            <span class="sf-group-count">{{ $periodDues->count() }} {{ \Illuminate\Support\Str::plural('fee', $periodDues->count()) }}</span></td>
                                        @php $groupLeft = $periodDues->whereNotIn('status', ['cleared', 'waived'])->sum(fn ($d) => $d->balance); @endphp
                                        @if($groupLeft > 0)
                                            <td class="text-end sf-amount">{{ money_inr($groupLeft, false) }}</td>
                                            <td colspan="2" class="sf-group-hint">to pay</td>
                                        @else
                                            <td></td>
                                            <td colspan="2"><span class="sf-tag sf-tone-green"><i class="ti ti-check"></i> Settled</span></td>
                                        @endif
                                    </tr>
                                @endif
                                <tr class="{{ $isNext ? 'sf-next' : '' }} {{ $due->isSettled() ? 'sf-settled' : '' }}" wire:key="due-{{ $due->id }}">
                                    <td>
                                        <div class="fw-semibold">{{ $due->fee_head }}</div>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            @if($isNext)<span class="sf-tag sf-tone-amber"><i class="ti ti-arrow-right"></i> Next to pay</span>@endif
                                            @if($due->advance)<span class="sf-tag sf-tone-green">{{ $due->status === 'cleared' ? 'Paid in advance' : 'Advance' }}</span>@endif
                                            @if(!$due->course_fee_id)<span class="sf-tag sf-tone-slate">Added by hand</span>@endif
                                            @foreach($due->discounts as $disc)
                                                <span class="sf-tag sf-tone-violet" title="Scholarship {{ optional(optional($disc->assignment)->scholarship)->name }}"><i class="ti ti-award"></i> {{ optional(optional($disc->assignment)->scholarship)->code }} − {{ money_inr($disc->amount, false) }}</span>
                                            @endforeach
                                        </div>
                                        @if($due->status === 'waived' && $due->remarks)<div class="sf-sub mt-1">Waived: {{ $due->remarks }}</div>@endif
                                        @if($pending > 0)<div class="sf-sub mt-1 text-info"><i class="ti ti-clock"></i> {{ money_inr($pending, false) }} being confirmed</div>@endif
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="{{ $chipLabel === 'Overdue' ? 'text-danger fw-semibold' : '' }}">{{ $due->due_date->format('d M Y') }}</div>
                                        @if($chipLabel === 'Overdue')<div class="sf-sub text-danger">{{ $due->due_date->diffForHumans(null, true) }} late</div>@endif
                                    </td>
                                    <td class="text-end sf-amount">
                                        @if((float) $due->discount > 0)
                                            <div class="text-muted text-decoration-line-through small">{{ money_inr($due->amount_due, false) }}</div>
                                            <div>{{ money_inr($due->net_amount, false) }}</div>
                                            <div class="sf-sub sf-disc">− {{ money_inr($due->discount, false) }} scholarship</div>
                                        @else
                                            {{ money_inr($due->amount_due, false) }}
                                        @endif
                                    </td>
                                    <td class="text-end sf-amount text-success">{{ $due->amount_paid > 0 ? money_inr($due->amount_paid, false) : '—' }}</td>
                                    <td class="text-end sf-amount fw-bold">{{ $due->balance > 0 ? money_inr($due->balance, false) : '—' }}</td>
                                    <td><span class="sf-status sf-tone-{{ $chipTone }}">{{ $chipLabel }}</span></td>
                                    <td class="text-end text-nowrap">
                                        @if($revocable->contains($due->id) && $canAdvance)
                                            <button type="button" class="btn btn-sm btn-outline-warning py-1 px-2" wire:click="ask('revoke', {{ $due->id }})" title="Cancel advance" aria-label="Cancel advance">
                                                <i class="ti ti-arrow-back-up"></i> Undo
                                            </button>
                                        @endif
                                        @can('fees.manage')
                                            @unless($due->isSettled())
                                                <button type="button" class="btn btn-sm sf-icon-btn" wire:click="confirmWaive({{ $due->id }})" title="Waive"><i class="ti ti-discount-2"></i></button>
                                            @endunless
                                            @if($due->payments->whereIn('status', ['pending_verification', 'success'])->isEmpty())
                                                <button type="button" class="btn btn-sm sf-icon-btn sf-icon-danger" wire:click="ask('delete', {{ $due->id }})" title="Remove"><i class="ti ti-trash"></i></button>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="sf-empty"><i class="ti ti-receipt-off"></i><div class="fw-semibold">No fees yet</div><div class="small">Generate them from the fee structure or add one by hand.</div></td></tr>
                            @endforelse
                        </tbody>
                        @if($upcoming->isNotEmpty())
                            {{-- Fees not charged yet: charged later (ER number / next semester), or now as an advance --}}
                            <tbody>
                                <tr class="sf-group sf-group-upcoming">
                                    <td colspan="7"><i class="ti ti-calendar-up"></i> Upcoming — not charged yet
                                        <span class="sf-group-count">{{ $upcoming->count() }} {{ \Illuminate\Support\Str::plural('fee', $upcoming->count()) }} · {{ money_inr($upcomingTotal, false) }}</span></td>
                                </tr>
                                @foreach($upcoming as $item)
                                    <tr class="sf-upcoming" wire:key="up-{{ $item['fee']->id }}">
                                        <td>
                                            <div class="fw-semibold">{{ $item['fee']->fee_head }} <span class="sf-sub fw-normal">· {{ $item['period'] }}</span></div>
                                            <div class="d-flex flex-wrap gap-1 mt-1 align-items-center">
                                                @if($item['advance_allowed'])<span class="sf-tag sf-tone-blue">Advance allowed</span>@endif
                                                <span class="sf-sub">{{ $item['when'] }}</span>
                                            </div>
                                        </td>
                                        <td class="text-nowrap text-muted">{{ $item['expected'] ? $item['expected']->format('d M Y') : '—' }}</td>
                                        <td class="text-end sf-amount text-muted">{{ money_inr($item['fee']->amount, false) }}</td>
                                        <td class="text-end text-muted">—</td>
                                        <td class="text-end text-muted">—</td>
                                        <td><span class="sf-status sf-tone-slate">Upcoming</span></td>
                                        <td class="text-end text-nowrap">
                                            @if($item['advance'] && $canAdvance)
                                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="ask('advance', {{ $item['fee']->id }})">
                                                    <i class="ti ti-player-track-next me-1"></i> Pay in advance
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endif
                    </table>
                </div>
            </div>

            {{-- Payments & receipts --}}
            <div class="sf-panel">
                <div class="sf-panel-head">
                    <div class="d-flex align-items-center gap-2">
                        <span class="sf-panel-icon"><i class="ti ti-history"></i></span>
                        <div>
                            <h5 class="mb-0">Payments &amp; receipts</h5>
                            <small class="text-muted">{{ $payments->count() }} {{ \Illuminate\Support\Str::plural('payment', $payments->count()) }}</small>
                        </div>
                    </div>
                    @can('payments.verify')
                        <a href="{{ route('admin.onboarding.payments') }}" class="btn btn-sm btn-light border"><i class="ti ti-checklist me-1"></i> Accounts queue</a>
                    @endcan
                </div>
                <div class="sf-payments">
                    @forelse($payments as $payment)
                        @php [$pTone, $pIcon] = $payTone[$payment->status] ?? ['slate', 'ti ti-point']; @endphp
                        <div class="sf-payment" wire:key="pay-{{ $payment->id }}">
                            <span class="sf-pay-icon sf-tone-{{ $pTone }}"><i class="{{ $pIcon }}"></i></span>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="fw-semibold">{{ optional($payment->due)->fee_head ?? 'Payment' }}</span>
                                    {!! $payment->status_html !!}
                                </div>
                                <div class="sf-sub">
                                    {{ optional($payment->paid_at)->format('d M Y') }} · {{ optional($payment->gateway)->name }}
                                    @if($payment->receipt_number) · <span class="sf-mono">{{ $payment->receipt_number }}</span>
                                    @elseif($payment->transaction_reference) · Ref <span class="sf-mono">{{ $payment->transaction_reference }}</span>@endif
                                    @if($payment->verifier) · confirmed by {{ $payment->verifier->name }}@endif
                                </div>
                                @if($payment->rejection_reason)<div class="sf-sub text-danger mt-1"><i class="ti ti-alert-circle"></i> {{ $payment->rejection_reason }}</div>@endif
                            </div>
                            <span class="sf-pay-amount {{ $payment->status === 'failed' ? 'text-decoration-line-through text-muted' : '' }}">{{ money_inr($payment->amount, false) }}</span>
                            <span class="d-flex gap-1">
                                @if($payment->proof_file_path)
                                    <a href="{{ route('admin.students.payments.proof', $payment->id) }}" target="_blank" class="btn btn-sm sf-icon-btn" title="Payment proof"><i class="ti ti-photo"></i></a>
                                @endif
                                @if($payment->status === 'success')
                                    <a href="{{ route('admin.students.payments.receipt', $payment->id) }}" target="_blank" class="btn btn-sm sf-icon-btn sf-icon-primary" title="Print receipt"><i class="ti ti-printer"></i></a>
                                @endif
                            </span>
                        </div>
                    @empty
                        <div class="sf-empty"><i class="ti ti-cash-off"></i><div class="fw-semibold">No payments yet</div></div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Record a payment --}}
        <div class="col-xl-4">
            @if($scholarships->isNotEmpty())
                {{-- Scholarships (Module 2C) --}}
                <div class="sf-panel mb-3 sf-schol">
                    <div class="sf-panel-head">
                        <div class="d-flex align-items-center gap-2">
                            <span class="sf-panel-icon sf-panel-icon-violet"><i class="ti ti-award"></i></span>
                            <div><h5 class="mb-0">Scholarships</h5><small class="text-muted">{{ money_inr($discountTotal, false) }} off the charged fees</small></div>
                        </div>
                    </div>
                    <div class="sf-panel-body py-2">
                        @foreach($scholarships as $a)
                            <div class="sf-schol-row {{ $a->status === 'active' ? '' : 'is-off' }}">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate">{{ optional($a->scholarship)->name }} <span class="sf-schol-code">{{ optional($a->scholarship)->code }}</span></div>
                                    <div class="sf-sub">{{ optional($a->scholarship)->value_label }} · {{ optional($a->scholarship)->periods_label }}</div>
                                    <div class="sf-sub">{{ $a->status === 'active' ? 'Given' : 'Revoked' }} {{ optional($a->status === 'active' ? $a->assigned_at : $a->revoked_at)->format('d M Y') }}{{ $a->status === 'active' && $a->assigner ? ' by ' . $a->assigner->name : '' }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold font-monospace sf-disc">− {{ money_inr($a->discount_total ?? 0, false) }}</div>
                                    @if($a->status === 'active' && $canAssignScholarship)
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 small" wire:click="openScholarshipRevoke({{ $a->id }})">Revoke</button>
                                    @elseif($a->status !== 'active')
                                        <span class="sf-tag sf-tone-slate">Revoked</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if($canAssignScholarship)
                            <a href="{{ route('admin.scholarships.assign') }}" class="btn btn-sm btn-light border w-100 mt-2"><i class="ti ti-award me-1"></i> Assign a scholarship</a>
                        @endif
                    </div>
                </div>
            @endif
            <div class="sf-panel sf-pay-form">
                <div class="sf-panel-head">
                    <div class="d-flex align-items-center gap-2">
                        <span class="sf-panel-icon sf-panel-icon-accent"><i class="ti ti-cash-register"></i></span>
                        <div>
                            <h5 class="mb-0">Record a payment</h5>
                            <small class="text-muted">GPay / UPI transfer or payment at the office.</small>
                        </div>
                    </div>
                </div>
                <div class="sf-panel-body">
                    @cannot('payments.collect')
                        <div class="sf-empty py-4"><i class="ti ti-lock"></i><div class="small">You do not have permission to record payments.</div></div>
                    @else
                        @if($payableDues->isEmpty())
                            <div class="sf-empty py-4"><i class="ti ti-circle-check text-success"></i><div class="fw-semibold">Nothing to collect</div>
                                <div class="small">All fees are paid, waived or already waiting for verification.</div></div>
                        @else
                            <div class="row g-3">
                                @include('livewire.admin.students.partials.select', ['name' => 'pay_due_id', 'label' => 'Fee', 'required' => true, 'col' => 12, 'live' => true, 'fieldIcon' => 'ti ti-receipt-2',
                                    'options' => $payableDues->mapWithKeys(fn ($d) => [$d->id => $d->fee_head . ' — ' . money_inr($d->payableBalance(), false) . ' left'])])
                                @include('livewire.admin.students.partials.input', ['name' => 'pay_amount', 'label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'required' => true, 'fieldIcon' => 'ti ti-currency-rupee', 'placeholder' => '0.00'])
                                @include('livewire.admin.students.partials.input', ['name' => 'pay_date', 'label' => 'Paid on', 'type' => 'date', 'required' => true, 'fieldIcon' => 'ti ti-calendar'])
                            </div>

                            <fieldset class="mt-3">
                                <legend class="form-label fw-medium small mb-2">How was it paid? <span class="text-danger">*</span></legend>
                                <div class="sf-methods">
                                    @foreach($gateways as $gateway)
                                        <input type="radio" class="btn-check" name="pay_gateway" id="gw{{ $gateway->id }}" value="{{ $gateway->id }}" wire:model="pay_gateway_id">
                                        <label class="sf-method" for="gw{{ $gateway->id }}">
                                            <i class="{{ $gatewayIcon($gateway) }}"></i>
                                            <span class="fw-semibold">{{ $gateway->name }}</span>
                                            <small>{{ $gateway->type === 'upi' ? 'UTR + screenshot' : ($gateway->code === 'cash' ? 'At the desk' : 'Reference needed') }}</small>
                                        </label>
                                    @endforeach
                                </div>
                                @error('pay_gateway_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </fieldset>

                            @if($selectedGateway)
                                <div class="row g-3 mt-1">
                                    @if($selectedGateway->code !== 'cash')
                                        @include('livewire.admin.students.partials.input', ['name' => 'pay_reference', 'label' => $selectedGateway->type === 'upi' ? 'UTR / UPI reference number' : 'Cheque / transaction reference',
                                            'required' => true, 'col' => 12, 'fieldIcon' => 'ti ti-hash', 'placeholder' => $selectedGateway->type === 'upi' ? '12 digits, from the UPI app' : ''])
                                    @endif
                                    @if($selectedGateway->type === 'upi')
                                        @include('livewire.admin.students.partials.input', ['name' => 'pay_upi', 'label' => "Payer's UPI ID", 'col' => 12, 'fieldIcon' => 'ti ti-at', 'placeholder' => 'Optional, e.g. name@okaxis'])
                                    @endif
                                    <div class="col-12">
                                        <label class="form-label fw-medium small" for="payProof">
                                            {{ $selectedGateway->type === 'upi' ? 'Payment screenshot' : 'Proof (bank slip / cheque photo)' }}
                                            @if($selectedGateway->type === 'upi')<span class="text-danger">*</span>@else <span class="text-muted fw-normal">(optional)</span>@endif
                                        </label>
                                        <label class="sf-drop @error('pay_proof') is-invalid @enderror" for="payProof">
                                            <i class="ti ti-cloud-upload"></i>
                                            <span>{{ $pay_proof ? $pay_proof->getClientOriginalName() : 'Choose a file' }}</span>
                                            <small>JPG, PNG or PDF · up to {{ config('camp.payment_proof_max_kb') / 1024 }} MB</small>
                                        </label>
                                        <input type="file" id="payProof" class="d-none" wire:model="pay_proof" accept=".jpg,.jpeg,.png,.pdf">
                                        <div wire:loading wire:target="pay_proof" class="small text-muted mt-1">Uploading…</div>
                                        @error('pay_proof') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                    @include('livewire.admin.students.partials.input', ['name' => 'pay_remarks', 'label' => 'Remarks', 'type' => 'textarea', 'col' => 12])
                                </div>
                            @endif

                            <button type="button" class="btn btn-primary w-100 mt-3 py-2" wire:click="recordPayment" wire:loading.attr="disabled" wire:target="recordPayment,pay_proof">
                                <span wire:loading.remove wire:target="recordPayment"><i class="ti ti-device-floppy me-1"></i> Record payment</span>
                                <span wire:loading wire:target="recordPayment"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                            </button>
                            @can('payments.verify')
                                <div class="sf-confirm-note is-auto"><i class="ti ti-circle-check"></i> Recorded by Accounts: the payment is confirmed at once — receipt issued and the fee updated, no approval needed.</div>
                            @else
                                <div class="sf-confirm-note"><i class="ti ti-info-circle"></i> Accounts confirms each payment before a receipt is issued.</div>
                            @endcan
                        @endif
                    @endcannot
                </div>
            </div>
        </div>
    </div>

    {{-- Add fee modal --}}
    <div class="modal fade" id="addDueModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content sf-modal">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2"><span class="sf-panel-icon"><i class="ti ti-receipt-2"></i></span><h5 class="modal-title mb-0">Add a fee</h5></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">A one-off fee for this student only (e.g. uniform, re-exam). Course fees come from the fee structure.</p>
                    <div class="row g-3">
                        @include('livewire.admin.students.partials.input', ['name' => 'due_fee_head', 'label' => 'Fee', 'required' => true, 'col' => 12, 'placeholder' => 'e.g. Uniform fee', 'fieldIcon' => 'ti ti-receipt-2'])
                        @include('livewire.admin.students.partials.input', ['name' => 'due_amount', 'label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'required' => true, 'fieldIcon' => 'ti ti-currency-rupee', 'placeholder' => '0.00'])
                        @include('livewire.admin.students.partials.input', ['name' => 'due_date', 'label' => 'Due date', 'type' => 'date', 'required' => true, 'fieldIcon' => 'ti ti-calendar'])
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="addDue"><i class="ti ti-plus me-1"></i> Add fee</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Confirmation pop-up (pay in advance / cancel advance / remove fee) — same design as the delete modal --}}
    <div class="modal fade" id="feeConfirmModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    @if($confirmInfo)
                        <i class="{{ $confirmInfo['icon'] }} text-{{ $confirmInfo['tone'] }} fs-1 mb-3"></i>
                        <h5>{{ $confirmInfo['title'] }}</h5>
                        <p class="text-muted mb-4">{{ $confirmInfo['text'] }}</p>
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-{{ $confirmInfo['tone'] === 'danger' ? 'danger' : 'primary' }} px-4" wire:click="confirmed" wire:loading.attr="disabled">{{ $confirmInfo['button'] }}</button>
                        </div>
                    @else
                        <span class="spinner-border spinner-border-sm text-secondary"></span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Waive modal --}}
    <div class="modal fade" id="waiveModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content sf-modal">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2"><span class="sf-panel-icon sf-panel-icon-warn"><i class="ti ti-discount-2"></i></span><h5 class="modal-title mb-0">Waive fee</h5></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">The remaining balance of this fee will no longer be collected. This is recorded in the audit trail.</p>
                    @include('livewire.admin.students.partials.input', ['name' => 'waiveReason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'col' => 12])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" wire:click="waive">Waive fee</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Revoke scholarship --}}
    <div class="modal fade" id="scholarshipRevokeModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content sf-modal">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2"><span class="sf-panel-icon sf-panel-icon-violet"><i class="ti ti-award-off"></i></span><h5 class="modal-title mb-0">Revoke scholarship</h5></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">The discount is removed from fees without any payment. Fees already paid (or with a payment waiting) keep their discount — no refund, no extra charge.</p>
                    @include('livewire.admin.students.partials.input', ['name' => 'revokeReason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'col' => 12])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" wire:click="revokeScholarship">Revoke</button>
                </div>
            </div>
        </div>
    </div>

    @include('livewire.admin.onboarding.partials.styles')
    <style>
        .sf-ui .sf-tone-violet { background: #EDE9FE; color: #5B21B6; }
        .sf-ui .sf-disc { color: #6D28D9; }
        .sf-ui .sf-panel-icon-violet { background: #EDE9FE; color: #6D28D9; }
        .sf-ui .sf-schol-row { display: flex; justify-content: space-between; gap: 10px; padding: 10px 0; border-bottom: 1px dashed #EEE; }
        .sf-ui .sf-schol-row:last-of-type { border-bottom: 0; }
        .sf-ui .sf-schol-row.is-off { opacity: .55; }
        .sf-ui .sf-schol-code { padding: 0 6px; border-radius: 5px; background: #F5F3FF; color: #6D28D9; font-size: 11px; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .sf-ui { --sf-border: #E5E7EB; --sf-soft: #F1F2F4; --sf-ink: #111827; --sf-muted: #6B7280; --sf-accent: #F26522; }
        .sf-ui .min-w-0 { min-width: 0; }

        /* header */
        .sf-ui .sf-hero { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 16px 24px; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--sf-border);
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .sf-ui .sf-hero h2 { font-size: 22px; color: var(--sf-ink); }
        .sf-ui .sf-photo { width: 72px; height: 72px; border-radius: 16px; object-fit: cover; flex-shrink: 0; border: 3px solid #fff; box-shadow: 0 4px 12px rgba(16, 24, 40, .12); }
        .sf-ui .sf-initials { display: inline-flex; align-items: center; justify-content: center; background: #FEF0E7; color: var(--sf-accent); font-weight: 700; font-size: 22px; }
        .sf-ui .sf-meta { display: flex; flex-wrap: wrap; gap: 4px 16px; margin-top: 4px; font-size: 13px; color: #4B5563; }
        .sf-ui .sf-meta span { display: inline-flex; align-items: center; gap: 4px; }
        .sf-ui .sf-code { padding: 1px 7px; border-radius: 6px; background: #EEF2FF; color: #4338CA; font-weight: 600; font-size: 11.5px; }
        .sf-ui .sf-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .sf-ui .sf-chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; background: #fff; border: 1px solid var(--sf-border); font-size: 12.5px; color: #374151; white-space: nowrap; }
        .sf-ui .sf-chip i { color: #9CA3AF; }
        .sf-ui .sf-chip-er { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-weight: 600; border-color: #FDBA8C; color: #C2410C; background: #FFF7ED; }
        .sf-ui .sf-chip-er i { color: #F26522; }
        .sf-ui .sf-chip-period { background: #EDE9FE; border-color: #DDD6FE; color: #6D28D9; font-weight: 600; }
        .sf-ui .sf-chip-period i { color: #7C3AED; }
        .sf-ui .sf-balance { justify-self: end; display: flex; flex-direction: column; gap: 2px; min-width: 240px; padding: 14px 20px; border-radius: 14px; color: #fff; background: linear-gradient(135deg, #1F2937 0%, #111827 100%); box-shadow: 0 8px 20px rgba(17, 24, 39, .25); }
        .sf-ui .sf-balance-label { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #F9A26C; }
        .sf-ui .sf-balance-value { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 26px; font-weight: 700; }
        .sf-ui .sf-balance-sub { font-size: 12px; color: #9CA3AF; }

        /* summary */
        .sf-ui .sf-summary { position: relative; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); background: #fff; border: 1px solid var(--sf-border); border-radius: 14px; overflow: hidden; padding-bottom: 6px; }
        .sf-ui .sf-sum-item { display: flex; flex-direction: column; gap: 1px; padding: 14px 20px; border-right: 1px solid var(--sf-soft); }
        .sf-ui .sf-sum-item:nth-child(4) { border-right: 0; }
        .sf-ui .sf-sum-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--sf-muted); display: inline-flex; align-items: center; gap: 6px; }
        .sf-ui .sf-sum-value { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 20px; font-weight: 700; color: var(--sf-ink); }
        .sf-ui .sf-sum-hint { font-size: 12px; color: var(--sf-muted); }
        .sf-ui .sf-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
        .sf-ui .sf-dot-green { background: #22C55E; } .sf-ui .sf-dot-amber { background: #F59E0B; } .sf-ui .sf-dot-blue { background: #93C5FD; }
        .sf-ui .sf-progress { position: absolute; left: 0; right: 0; bottom: 0; height: 6px; display: flex; background: #DBEAFE; }
        .sf-ui .sf-progress-paid { background: #22C55E; } .sf-ui .sf-progress-due { background: #F59E0B; }
        .sf-ui .sf-note { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 12px; background: #FFFBEB; border: 1px solid #FDE68A; font-size: 13px; color: #78350F; }
        .sf-ui .sf-note i { font-size: 18px; color: #D97706; }
        .sf-ui .sf-note a { font-weight: 600; color: #B45309; }

        /* panels */
        .sf-ui .sf-panel { background: #fff; border: 1px solid var(--sf-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .sf-ui .sf-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 18px; border-bottom: 1px solid var(--sf-soft); }
        .sf-ui .sf-panel-head h5 { font-size: 16px; font-weight: 700; color: var(--sf-ink); }
        .sf-ui .sf-panel-icon { width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; background: #F3F4F6; color: #4B5563; }
        .sf-ui .sf-panel-icon-accent { background: #FEF0E7; color: var(--sf-accent); }
        .sf-ui .sf-panel-icon-warn { background: #FEF3C7; color: #B45309; }
        .sf-ui .sf-panel-body { padding: 16px 18px 18px; }
        .sf-ui .sf-pay-form { position: sticky; top: 76px; }

        /* fees table */
        .sf-ui .sf-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--sf-muted); padding: 10px 10px; white-space: nowrap; border-bottom: 1px solid var(--sf-border); }
        .sf-ui .sf-table td { padding: 11px 10px; border-color: var(--sf-soft); font-size: 13.5px; }
        .sf-ui .sf-table td:first-child, .sf-ui .sf-table th:first-child { padding-left: 16px; }
        .sf-ui .sf-table td:last-child { padding-right: 14px; }
        .sf-ui .sf-group td { background: #F8FAFC; font-size: 12.5px; font-weight: 700; color: #334155; padding-top: 8px; padding-bottom: 8px; }
        .sf-ui .sf-group i { color: var(--sf-accent); margin-right: 3px; }
        .sf-ui .sf-group-count { margin-left: 6px; font-weight: 500; color: #94A3B8; }
        .sf-ui .sf-group-hint { font-weight: 500 !important; color: #94A3B8 !important; }
        .sf-ui .sf-group-upcoming td { background: #EFF6FF; color: #1E40AF; }
        .sf-ui .sf-group-upcoming i { color: #3B82F6; }
        .sf-ui .sf-next td { background: #FFFBEB; }
        .sf-ui .sf-next td:first-child { box-shadow: inset 4px 0 0 #F59E0B; }
        .sf-ui .sf-settled td { color: #6B7280; }
        .sf-ui .sf-upcoming td { background: #FCFDFF; }
        .sf-ui .sf-amount { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 13px; white-space: nowrap; }
        .sf-ui .sf-sub { font-size: 12px; color: var(--sf-muted); }
        .sf-ui .sf-mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 12px; color: #374151; }
        .sf-ui .sf-tag { display: inline-flex; align-items: center; gap: 3px; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: var(--tone-soft); color: var(--tone); }
        .sf-ui .sf-status { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: var(--tone-soft); color: var(--tone); }
        .sf-ui .sf-tone-green { --tone: #15803D; --tone-soft: #DCFCE7; } .sf-ui .sf-tone-amber { --tone: #B45309; --tone-soft: #FEF3C7; }
        .sf-ui .sf-tone-red { --tone: #B91C1C; --tone-soft: #FEE2E2; } .sf-ui .sf-tone-blue { --tone: #1D4ED8; --tone-soft: #DBEAFE; }
        .sf-ui .sf-tone-slate { --tone: #475569; --tone-soft: #F1F5F9; }
        .sf-ui .sf-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--sf-border); background: #fff; color: #4B5563; border-radius: 8px; }
        .sf-ui .sf-icon-btn:hover { border-color: #FDBA8C; color: var(--sf-accent); }
        .sf-ui .sf-icon-danger:hover { border-color: #FCA5A5; color: #DC2626; }
        .sf-ui .sf-icon-primary { color: var(--sf-accent); }
        .sf-ui .sf-empty { text-align: center; color: var(--sf-muted); padding: 36px 12px !important; }
        .sf-ui .sf-empty > i { font-size: 32px; color: #D1D5DB; display: block; margin-bottom: 6px; }

        /* payments list */
        .sf-ui .sf-payment { display: flex; align-items: center; gap: 12px; padding: 12px 18px; border-bottom: 1px solid var(--sf-soft); }
        .sf-ui .sf-payment:last-child { border-bottom: 0; }
        .sf-ui .sf-payment:hover { background: #FFFBF7; }
        .sf-ui .sf-pay-icon { width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; background: var(--tone-soft); color: var(--tone); }
        .sf-ui .sf-pay-amount { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-weight: 700; font-size: 14px; white-space: nowrap; color: var(--sf-ink); }

        /* payment form */
        .sf-ui .sf-methods { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .sf-ui .sf-method { display: flex; flex-direction: column; gap: 1px; padding: 10px 12px; border: 1px solid var(--sf-border); border-radius: 10px; cursor: pointer; background: #fff; transition: border-color .15s, background .15s; margin: 0; }
        .sf-ui .sf-method i { font-size: 20px; color: #9CA3AF; margin-bottom: 2px; }
        .sf-ui .sf-method span { font-size: 13.5px; color: var(--sf-ink); }
        .sf-ui .sf-method small { font-size: 11.5px; color: var(--sf-muted); }
        .sf-ui .sf-method:hover { border-color: #FDBA8C; }
        .sf-ui .btn-check:checked + .sf-method { border-color: var(--sf-accent); background: #FFF7F2; box-shadow: 0 0 0 1px var(--sf-accent); }
        .sf-ui .btn-check:checked + .sf-method i { color: var(--sf-accent); }
        .sf-ui .sf-drop { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 14px; border: 1.5px dashed #D1D5DB; border-radius: 10px; background: #FAFAFA; cursor: pointer; text-align: center; margin: 0; }
        .sf-ui .sf-drop i { font-size: 24px; color: var(--sf-accent); }
        .sf-ui .sf-drop span { font-size: 13px; font-weight: 600; color: var(--sf-ink); overflow-wrap: anywhere; }
        .sf-ui .sf-drop small { font-size: 11.5px; color: var(--sf-muted); }
        .sf-ui .sf-drop:hover { border-color: var(--sf-accent); background: #FFF7F2; }
        .sf-ui .sf-drop.is-invalid { border-color: #DC2626; }
        .sf-ui .sf-confirm-note { display: flex; gap: 8px; margin-top: 10px; padding: 9px 12px; border-radius: 10px; background: #F9FAFB; font-size: 12.5px; color: var(--sf-muted); }
        .sf-ui .sf-confirm-note.is-auto { background: #F0FDF4; color: #166534; }
        .sf-ui .sf-modal { border: 0; border-radius: 14px; }
        /* icons inside the form fields */
        .sf-ui .fx-icon-field { position: relative; }
        .sf-ui .fx-icon-field .fx-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9CA3AF; font-size: 16px; pointer-events: none; z-index: 2; }
        .sf-ui .fx-icon-field .form-control, .sf-ui .fx-icon-field .form-select { padding-left: 36px; }
        .sf-ui .fx-icon-field:focus-within .fx-icon { color: var(--sf-accent); }

        @media (max-width: 1199.98px) { .sf-ui .sf-pay-form { position: static; } }
        @media (max-width: 767.98px) {
            .sf-ui .sf-hero { grid-template-columns: 1fr; }
            .sf-ui .sf-balance { width: 100%; justify-self: stretch; }
            .sf-ui .sf-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .sf-ui .sf-sum-item:nth-child(2) { border-right: 0; }
            .sf-ui .sf-sum-item:nth-child(-n+2) { border-bottom: 1px solid var(--sf-soft); }
            .sf-ui .sf-payment { flex-wrap: wrap; }
        }
    </style>
</div>

<script>
document.addEventListener('livewire:load', function () {
    window.addEventListener('open-fee-modal', e => bootstrap.Modal.getOrCreateInstance(document.getElementById(e.detail.id)).show());
    window.addEventListener('close-fee-modal', e => {
        const instance = bootstrap.Modal.getInstance(document.getElementById(e.detail.id));
        if (instance) {
            instance.hide();
        }
    });
});
</script>
