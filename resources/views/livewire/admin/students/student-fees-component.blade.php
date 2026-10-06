@php $user = auth()->user(); @endphp
<div class="content">
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">{{ $student->full_name }} {!! $student->status_html !!}</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.students') }}">Students</a></li>
                    <li class="breadcrumb-item active">Fees &amp; Payments</li>
                </ol>
            </nav>
        </div>
        <span class="text-muted small">{{ optional($student->course)->name }} · {{ optional($student->institute)->name }}</span>
    </div>

    @include('livewire.admin.students.partials.student-nav', ['student' => $student, 'active' => 'fees'])

    <div class="row g-3">
        <div class="col-xl-7">
            {{-- Fees --}}
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="fw-semibold mb-0">Fees</h5>
                    <span class="text-muted">Still to pay <b class="amount fs-15 text-dark ms-1">{{ money_inr($outstanding, false) }}</b></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Fee</th><th>Due</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($dues as $due)
                                @php $pending = $due->payments->where('status', 'pending_verification')->sum('amount'); @endphp
                                <tr>
                                    <td>
                                        <span class="fw-semibold">{{ $due->fee_head }}</span>
                                        @if($due->status === 'waived' && $due->remarks)<div class="small text-muted">Waived: {{ $due->remarks }}</div>@endif
                                        @if($pending > 0)<div class="small text-info">{{ money_inr($pending, false) }} being confirmed</div>@endif
                                    </td>
                                    <td class="small text-nowrap {{ !$due->isSettled() && $due->due_date->isPast() ? 'text-danger fw-medium' : '' }}">{{ $due->due_date->format('d M Y') }}</td>
                                    <td class="text-end amount">{{ money_inr($due->amount_due, false) }}</td>
                                    <td class="text-end amount">{{ money_inr($due->amount_paid, false) }}</td>
                                    <td class="text-end amount fw-semibold">{{ money_inr($due->balance, false) }}</td>
                                    <td>{!! $due->status_html !!}</td>
                                    <td class="text-end text-nowrap">
                                        @can('fees.manage')
                                            @unless($due->isSettled())
                                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="confirmWaive({{ $due->id }})" title="Waive">Waive</button>
                                            @endunless
                                            @if($due->payments->whereIn('status', ['pending_verification', 'success'])->isEmpty())
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteDue({{ $due->id }})"
                                                        onclick="confirm('Remove this fee?') || event.stopImmediatePropagation()" title="Remove"><i class="ti ti-trash"></i></button>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No fees yet. Generate them from the fee structure or add one.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @can('fees.manage')
                    <div class="card-footer bg-white d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-primary" wire:click="generateDues" wire:loading.attr="disabled">
                            <i class="ti ti-list-check me-1"></i> Generate from fee structure
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.dispatchEvent(new CustomEvent('open-fee-modal', {detail: {id: 'addDueModal'}}))">
                            <i class="ti ti-plus me-1"></i> Add fee
                        </button>
                    </div>
                @endcan
            </div>

            {{-- Payments & receipts --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white"><h5 class="fw-semibold mb-0">Payments &amp; receipts</h5></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Paid on</th><th>Fee</th><th>Method</th><th class="text-end">Amount</th><th>Reference / receipt</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $payment)
                                <tr>
                                    <td class="small text-nowrap">{{ optional($payment->paid_at)->format('d M Y') }}</td>
                                    <td>{{ optional($payment->due)->fee_head ?? '—' }}</td>
                                    <td class="small">{{ optional($payment->gateway)->name }}</td>
                                    <td class="text-end amount">{{ money_inr($payment->amount, false) }}</td>
                                    <td class="amount small">
                                        {{ $payment->receipt_number ?: ($payment->transaction_reference ?: '—') }}
                                        @if($payment->rejection_reason)<div class="text-danger" style="font-family: inherit;">{{ $payment->rejection_reason }}</div>@endif
                                    </td>
                                    <td>{!! $payment->status_html !!}</td>
                                    <td class="text-end text-nowrap">
                                        @if($payment->proof_file_path)
                                            <a href="{{ route('admin.students.payments.proof', $payment->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Payment proof"><i class="ti ti-photo"></i></a>
                                        @endif
                                        @if($payment->status === 'success')
                                            <a href="{{ route('admin.students.payments.receipt', $payment->id) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Receipt"><i class="ti ti-printer"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No payments yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @can('payments.verify')
                    <div class="card-footer bg-white small">
                        <a href="{{ route('admin.onboarding.payments') }}"><i class="ti ti-arrow-right me-1"></i>Verify pending payments in the Accounts queue</a>
                    </div>
                @endcan
            </div>
        </div>

        {{-- Record a payment --}}
        <div class="col-xl-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="fw-semibold mb-0">Record a payment</h5>
                    <small class="text-muted">GPay / UPI transfer or payment at the office. Online gateway payments are confirmed automatically once a gateway is set up.</small>
                </div>
                <div class="card-body">
                    @cannot('payments.collect')
                        <p class="text-muted small mb-0">You do not have permission to record payments.</p>
                    @else
                        @if($payableDues->isEmpty())
                            <p class="text-muted small mb-0">Nothing is payable: all fees are paid, waived or already waiting for verification.</p>
                        @else
                            <div class="row g-3">
                                @include('livewire.admin.students.partials.select', ['name' => 'pay_due_id', 'label' => 'Fee', 'required' => true, 'col' => 12, 'live' => true,
                                    'options' => $payableDues->mapWithKeys(fn ($d) => [$d->id => $d->fee_head . ' — ' . money_inr($d->payableBalance(), false) . ' left'])])
                                @include('livewire.admin.students.partials.input', ['name' => 'pay_amount', 'label' => 'Amount (₹)', 'type' => 'number', 'step' => '0.01', 'required' => true])
                                @include('livewire.admin.students.partials.input', ['name' => 'pay_date', 'label' => 'Paid on', 'type' => 'date', 'required' => true])
                            </div>

                            <fieldset class="mt-3">
                                <legend class="form-label fw-medium small mb-2">How was it paid? <span class="text-danger">*</span></legend>
                                <div class="row g-2">
                                    @foreach($gateways as $gateway)
                                        <div class="col-6">
                                            <input type="radio" class="btn-check" name="pay_gateway" id="gw{{ $gateway->id }}" value="{{ $gateway->id }}" wire:model="pay_gateway_id">
                                            <label class="btn btn-outline-success w-100 text-start py-2" for="gw{{ $gateway->id }}">
                                                <span class="fw-semibold d-block">{{ $gateway->name }}</span>
                                                <span class="small">{{ $gateway->type === 'upi' ? 'UTR + screenshot' : ($gateway->code === 'cash' ? 'At the Accounts desk' : 'Reference required') }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('pay_gateway_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </fieldset>

                            @if($selectedGateway)
                                <div class="row g-3 mt-1">
                                    @if($selectedGateway->code !== 'cash')
                                        @include('livewire.admin.students.partials.input', ['name' => 'pay_reference', 'label' => $selectedGateway->type === 'upi' ? 'UTR / UPI reference number' : 'Cheque / transaction reference',
                                            'required' => true, 'col' => $selectedGateway->type === 'upi' ? 6 : 12, 'placeholder' => $selectedGateway->type === 'upi' ? '12 digits, from the UPI app' : ''])
                                    @endif
                                    @if($selectedGateway->type === 'upi')
                                        @include('livewire.admin.students.partials.input', ['name' => 'pay_upi', 'label' => "Payer's UPI ID", 'placeholder' => 'Optional, e.g. name@okaxis'])
                                    @endif
                                    <div class="col-12">
                                        <label class="form-label fw-medium small">
                                            {{ $selectedGateway->type === 'upi' ? 'Payment screenshot' : 'Proof (bank slip / cheque photo)' }}
                                            @if($selectedGateway->type === 'upi')<span class="text-danger">*</span>@else <span class="text-muted">(optional)</span>@endif
                                        </label>
                                        <input type="file" class="form-control @error('pay_proof') is-invalid @enderror" wire:model="pay_proof" accept=".jpg,.jpeg,.png,.pdf">
                                        <div wire:loading wire:target="pay_proof" class="small text-muted mt-1">Uploading…</div>
                                        @error('pay_proof') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        <small class="text-muted">Shows the amount, date and reference. JPG, PNG or PDF, up to {{ config('camp.payment_proof_max_kb') / 1024 }} MB.</small>
                                    </div>
                                    @include('livewire.admin.students.partials.input', ['name' => 'pay_remarks', 'label' => 'Remarks', 'type' => 'textarea', 'col' => 12])
                                </div>
                            @endif

                            <button type="button" class="btn btn-primary mt-3 px-4" wire:click="recordPayment" wire:loading.attr="disabled" wire:target="recordPayment,pay_proof">
                                <i class="ti ti-device-floppy me-1"></i> Record payment
                            </button>
                            <p class="small text-muted mt-2 mb-0">Accounts confirms each payment before a receipt is issued.</p>
                        @endif
                    @endcannot
                </div>
            </div>
        </div>
    </div>

    {{-- Add fee modal --}}
    <div class="modal fade" id="addDueModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Add fee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        @include('livewire.admin.students.partials.input', ['name' => 'due_fee_head', 'label' => 'Fee', 'required' => true, 'col' => 12, 'placeholder' => 'e.g. Uniform fee'])
                        @include('livewire.admin.students.partials.input', ['name' => 'due_amount', 'label' => 'Amount (₹)', 'type' => 'number', 'step' => '0.01', 'required' => true])
                        @include('livewire.admin.students.partials.input', ['name' => 'due_date', 'label' => 'Due date', 'type' => 'date', 'required' => true])
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="addDue">Add fee</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Waive modal --}}
    <div class="modal fade" id="waiveModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Waive fee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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

    @include('livewire.admin.onboarding.partials.styles')
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
