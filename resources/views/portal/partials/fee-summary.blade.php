{{-- Fees of the application (sidebar of every registration step, status page). "Pay now" appears once the
     required documents are uploaded (PortalProgress::paymentUnlocked). Params: $student --}}
@php
    $summaryDues = $student->dues()->with('payments')->orderBy('due_date')->get();
    $summaryOutstanding = $summaryDues->whereNotIn('status', ['cleared', 'waived'])->sum(fn ($d) => $d->balance);
    $summaryTotal = $summaryDues->where('status', '!=', 'waived')->sum(fn ($d) => $d->net_amount);
    $summaryPaidPct = $summaryTotal > 0 ? (int) round(max(0, min(1, ($summaryTotal - $summaryOutstanding) / $summaryTotal)) * 100) : 0;
    $firstPayable = $summaryDues->whereNotIn('status', ['cleared', 'waived'])->first(fn ($d) => $d->payableBalance() > 0);
    $canPay = \App\Support\PortalProgress::paymentUnlocked($student);
@endphp
@if($summaryDues->isNotEmpty())
    <section class="card" style="gap: 12px;" aria-label="Your fees">
        <div class="row-between">
            <h2 style="font-size: 16px;">Fees</h2>
            <span class="badge {{ $summaryOutstanding <= 0 ? 'b-ok' : 'b-warn' }}">{{ $summaryOutstanding <= 0 ? 'All paid' : $summaryPaidPct . '% paid' }}</span>
        </div>
        <div class="feebar" aria-hidden="true"><span style="width: {{ $summaryPaidPct }}%;"></span></div>
        @php $nextDue = $summaryDues->whereNotIn('status', ['cleared', 'waived'])->first(); @endphp
        @if($nextDue)
            <div class="row-between" style="font-size: 13.5px; padding: 10px 12px; border-radius: 12px; background: rgba(242, 245, 252, .85); border: 1px solid var(--line-2);">
                <span style="display: flex; flex-direction: column; min-width: 0;"><span class="muted" style="font-size: 12px;">Next due · {{ $nextDue->due_date->format('d M') }}</span><b style="font-weight: 700;">{{ $nextDue->fee_head }}</b></span>
                <b class="mono" style="font-weight: 500;">{{ money_inr($nextDue->balance, false) }}</b>
            </div>
        @endif
        <details class="fee-all" wire:ignore.self @if($summaryDues->count() <= 3) open @endif>
        <summary>All fees ({{ $summaryDues->count() }})</summary>
        <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 8px;">
            @foreach($summaryDues as $due)
                @php
                    $chip = match (true) {
                        $due->status === 'cleared' => ['b-ok', 'Paid'],
                        $due->status === 'waived' => ['b-muted', 'Waived'],
                        $due->payments->where('status', 'pending_verification')->isNotEmpty() => ['b-info', 'Confirming'],
                        $due->status === 'partial' => ['b-warn', 'Part paid'],
                        default => ['b-muted', 'Due ' . $due->due_date->format('d M')],
                    };
                @endphp
                <div style="display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 10px; align-items: center; font-size: 13.5px;">
                    <span style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $due->fee_head }}</span>
                    <span class="badge {{ $chip[0] }}" style="height: 22px; font-size: 11.5px;">{{ $chip[1] }}</span>
                    <span class="mono muted" style="font-size: 12.5px;">{{ money_inr($due->isSettled() ? $due->net_amount : $due->balance, false) }}@if((float) $due->discount > 0)<span class="fee-schol-mini" title="Scholarship"> ★</span>@endif</span>
                </div>
            @endforeach
        </div>
        </details>
        <div class="row-between" style="border-top: 1px solid var(--line); padding-top: 10px; font-size: 13.5px;">
            <span class="muted">Still to pay</span><b class="mono" style="font-size: 16px;">{{ money_inr($summaryOutstanding, false) }}</b>
        </div>
        @if(!$canPay)
            <span class="hint">You can pay once all required documents are uploaded.</span>
        @elseif($firstPayable)
            <a href="{{ route('portal.payment', ['due' => $firstPayable->id]) }}" class="btn btn-primary btn-sm">Pay now</a>
        @elseif($summaryOutstanding <= 0)
            <a href="{{ route('portal.payment') }}" class="link-btn" style="font-size: 13.5px;">View receipts</a>
        @endif
    </section>
@endif
