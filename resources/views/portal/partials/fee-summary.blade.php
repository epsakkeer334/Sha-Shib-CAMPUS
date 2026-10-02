{{-- Fees of the application with "Pay now" (shown on every registration step). Params: $student --}}
@php
    $summaryDues = $student->dues()->with('payments')->orderBy('due_date')->get();
    $summaryOutstanding = $summaryDues->whereNotIn('status', ['cleared', 'waived'])->sum(fn ($d) => $d->balance);
    $firstPayable = $summaryDues->whereNotIn('status', ['cleared', 'waived'])->first(fn ($d) => $d->payableBalance() > 0);
@endphp
@if($summaryDues->isNotEmpty())
    <section class="card" style="gap: 14px; padding: 20px 24px;" aria-label="Your fees">
        <div class="row-between">
            <h2 style="font-size: 16px;">Your fees</h2>
            <span style="color: var(--ink-2); font-size: 14px;">Still to pay <b class="mono" style="font-size: 17px; color: var(--ink);">{{ money_inr($summaryOutstanding, false) }}</b></span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            @foreach($summaryDues as $due)
                @php
                    $chip = match (true) {
                        $due->status === 'cleared' => ['b-ok', 'Paid'],
                        $due->status === 'waived' => ['b-muted', 'Waived'],
                        $due->payments->where('status', 'pending_verification')->isNotEmpty() => ['b-info', 'Being confirmed'],
                        $due->status === 'partial' => ['b-warn', 'Part paid'],
                        default => ['b-muted', 'Due ' . $due->due_date->format('d M')],
                    };
                @endphp
                <div style="display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 12px; align-items: center; font-size: 14px;">
                    <span style="font-weight: 500;">{{ $due->fee_head }}</span>
                    <span class="mono">{{ money_inr($due->isSettled() ? $due->amount_due : $due->balance, false) }}</span>
                    <span class="badge {{ $chip[0] }}" style="height: 24px; font-size: 12px;">{{ $chip[1] }}</span>
                </div>
            @endforeach
        </div>
        @if($firstPayable)
            <a href="{{ route('portal.payment', ['due' => $firstPayable->id]) }}" class="btn btn-primary btn-sm" style="align-self: flex-start;">Pay now</a>
        @elseif($summaryOutstanding <= 0)
            <span class="muted" style="font-size: 14px;">All fees are paid. <a href="{{ route('portal.payment') }}">Receipts</a></span>
        @endif
    </section>
@endif
