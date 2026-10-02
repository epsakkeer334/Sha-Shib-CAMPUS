<div class="content">
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Payment verification</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Student Onboarding</li>
                    <li class="breadcrumb-item active">Payment verification · Gate 2</li>
                </ol>
            </nav>
        </div>
        @include('livewire.admin.onboarding.partials.queue-tabs', ['tabs' => [
            'pending' => 'To verify · ' . $pendingCount,
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'gate' => 'Fee gate · ' . $gateCount,
        ]])
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <label class="form-label small text-muted mb-1" for="paySearch">Search</label>
            <input id="paySearch" type="search" class="form-control" placeholder="{{ $tab === 'gate' ? 'Student name' : 'Student, UTR, reference or receipt' }}" wire:model.debounce.400ms="search">
        </div>
        @if($tab !== 'gate')
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1" for="payMethod">Method</label>
                <select id="payMethod" class="form-select" wire:model="method">
                    <option value="">All methods</option>
                    @foreach($methods as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    @if($tab === 'gate')
        {{-- Gate 2 · Fee clearance --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="fw-semibold mb-0">Gate 2 · Fee clearance</h5>
                <small class="text-muted">Students whose documents are approved (Gate 1) and who are waiting on Accounts</small>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Student</th><th>Documents gate</th><th class="text-end">Outstanding</th><th>Dues</th><th class="text-end"><span class="visually-hidden">Action</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse($gateStudents as $item)
                            @php
                                $blocker = $onboarding->gateBlocker($item, \App\Models\Admin\EnrollmentApproval::FEES);
                                $pendingPayments = $item->payments->where('status', 'pending_verification')->count();
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.students.fees', $item->id) }}" class="fw-semibold text-dark">{{ $item->full_name }}</a>
                                    <div class="small text-muted">{{ optional($item->course)->code }}</div>
                                </td>
                                <td><span class="badge badge-soft-success">Approved</span></td>
                                <td class="text-end amount">{{ money_inr($item->outstandingAmount(), false) }}</td>
                                <td class="small">
                                    @if($item->dues->isEmpty()) <span class="text-danger">No fees set up</span>
                                    @elseif($pendingPayments) {{ $pendingPayments }} payment(s) awaiting verification
                                    @elseif($item->outstandingAmount() > 0) Not fully paid
                                    @else All cleared @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-success btn-sm" wire:click="approveGate({{ $item->id }})" wire:loading.attr="disabled"
                                            @if($blocker) disabled title="{{ $blocker }}" @endif>Approve gate</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No students are waiting for fee clearance.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $gateStudents->links() }}</div>
        </div>
    @else
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card shadow-sm border-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Student</th><th>Fee</th><th>Method</th><th class="text-end">Amount</th><th>Reference</th><th>{{ $tab === 'pending' ? 'Submitted' : 'Decided' }}</th></tr>
                            </thead>
                            <tbody>
                                @forelse($payments as $payment)
                                    <tr wire:click="select({{ $payment->id }})" style="cursor: pointer;" class="{{ optional($selected)->id === $payment->id ? 'table-success' : '' }}">
                                        <td>
                                            <span class="fw-semibold d-block">{{ optional($payment->student)->full_name }}</span>
                                            <span class="small text-muted">{{ optional(optional($payment->student)->course)->code }}</span>
                                        </td>
                                        <td>{{ optional($payment->due)->fee_head ?? '—' }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ optional($payment->gateway)->name }}</span></td>
                                        <td class="text-end amount">{{ money_inr($payment->amount, false) }}</td>
                                        <td class="amount small">{{ $payment->receipt_number ?: ($payment->transaction_reference ?: '—') }}</td>
                                        <td class="small text-nowrap">{{ ($tab === 'pending' ? $payment->created_at : $payment->verified_at)?->format('d M, H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">No payments here.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3">{{ $payments->links() }}</div>
                </div>
            </div>

            {{-- Detail panel --}}
            <div class="col-xl-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        @if(!$selected)
                            <div class="text-center text-muted py-5"><i class="ti ti-hand-click fs-1 d-block mb-2"></i> Select a payment.</div>
                        @else
                            @php
                                $due = $selected->due;
                                $balance = $due ? $due->balance : null;
                            @endphp
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                <a href="{{ route('admin.students.fees', $selected->student_id) }}" class="fw-semibold fs-16 text-dark">{{ optional($selected->student)->full_name }}</a>
                                {!! $selected->status_html !!}
                            </div>

                            @if($selected->proof_file_path)
                                <a href="{{ route('admin.students.payments.proof', $selected->id) }}" target="_blank"
                                   class="doc-preview d-flex align-items-center justify-content-center rounded mb-3 text-decoration-none" style="height: 220px;">
                                    @if($selected->proof_is_image)
                                        <img src="{{ route('admin.students.payments.proof', $selected->id) }}" alt="Payment proof">
                                    @else
                                        <span class="text-muted small"><i class="ti ti-file-type-pdf fs-1 text-danger d-block"></i> Open payment proof</span>
                                    @endif
                                </a>
                            @endif

                            <dl class="row small mb-3">
                                <dt class="col-5 text-muted fw-normal">Amount</dt><dd class="col-7 text-end amount fw-semibold">{{ money_inr($selected->amount) }}</dd>
                                <dt class="col-5 text-muted fw-normal">Fee</dt><dd class="col-7 text-end">{{ optional($due)->fee_head ?? '—' }}</dd>
                                @if($due)<dt class="col-5 text-muted fw-normal">Due balance</dt><dd class="col-7 text-end amount">{{ money_inr($balance) }}</dd>@endif
                                <dt class="col-5 text-muted fw-normal">Method</dt><dd class="col-7 text-end">{{ optional($selected->gateway)->name }}</dd>
                                <dt class="col-5 text-muted fw-normal">Reference</dt><dd class="col-7 text-end amount">{{ $selected->transaction_reference ?: '—' }}</dd>
                                @if($selected->payer_upi_id)<dt class="col-5 text-muted fw-normal">Payer UPI</dt><dd class="col-7 text-end">{{ $selected->payer_upi_id }}</dd>@endif
                                <dt class="col-5 text-muted fw-normal">Paid on</dt><dd class="col-7 text-end">{{ optional($selected->paid_at)->format('d M Y') }}</dd>
                                <dt class="col-5 text-muted fw-normal">Recorded</dt><dd class="col-7 text-end">{{ $selected->created_at->format('d M Y, h:i A') }} · {{ optional($selected->recorder)->name }}</dd>
                                @if($selected->receipt_number)<dt class="col-5 text-muted fw-normal">Receipt</dt><dd class="col-7 text-end amount">{{ $selected->receipt_number }}</dd>@endif
                                @if($selected->verifier)<dt class="col-5 text-muted fw-normal">Decided by</dt><dd class="col-7 text-end">{{ $selected->verifier->name }} · {{ optional($selected->verified_at)->format('d M, H:i') }}</dd>@endif
                                @if($selected->rejection_reason)<dt class="col-5 text-muted fw-normal">Reason</dt><dd class="col-7 text-end text-danger">{{ $selected->rejection_reason }}</dd>@endif
                                @if($selected->remarks)<dt class="col-5 text-muted fw-normal">Remarks</dt><dd class="col-7 text-end">{{ $selected->remarks }}</dd>@endif
                            </dl>

                            @if($selected->status === 'pending_verification')
                                @if($due)
                                    @if(abs((float) $selected->amount - $balance) < 0.01)
                                        <p class="small rounded p-2 mb-3" style="background: #E3F0EA; color: #0B5544;">Amount matches the balance on this due.</p>
                                    @elseif((float) $selected->amount < $balance)
                                        <p class="small rounded p-2 mb-3" style="background: #FBEFD9; color: #6A3805;">Part payment — {{ money_inr($balance - $selected->amount) }} will remain on this due.</p>
                                    @else
                                        <p class="small rounded p-2 mb-3 bg-danger bg-opacity-10 text-danger">Amount is more than the due balance.</p>
                                    @endif
                                @endif

                                <label class="form-label small fw-medium" for="payReason">Reason (required to reject)</label>
                                <textarea id="payReason" class="form-control @error('reason') is-invalid @enderror" rows="2" wire:model.defer="reason"
                                          placeholder="e.g. UTR not found in bank statement"></textarea>
                                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="d-grid gap-2 mt-3" style="grid-template-columns: 1fr 1.4fr;">
                                    <button type="button" class="btn btn-outline-danger" wire:click="reject" wire:loading.attr="disabled">Reject</button>
                                    <button type="button" class="btn btn-success" wire:click="approve" wire:loading.attr="disabled">Approve &amp; issue receipt</button>
                                </div>
                                <p class="small text-muted mt-2 mb-0">Approval issues a receipt number, updates the due and notifies the student. Both actions are logged.</p>
                            @elseif($selected->status === 'success')
                                <a href="{{ route('admin.students.payments.receipt', $selected->id) }}" target="_blank" class="btn btn-outline-secondary w-100">
                                    <i class="ti ti-printer me-1"></i> Print receipt
                                </a>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.admin.onboarding.partials.styles')
</div>
