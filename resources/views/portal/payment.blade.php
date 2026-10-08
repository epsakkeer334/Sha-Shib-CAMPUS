@php
    $dueBadge = function ($due) {
        if ($due->status === 'cleared') return ['b-ok', 'Paid'];
        if ($due->status === 'waived') return ['b-muted', 'Waived'];
        if ($due->payments->where('status', 'pending_verification')->isNotEmpty()) return ['b-info', 'Being confirmed'];
        if ($due->status === 'partial') return ['b-warn', 'Part paid'];
        return [$due->due_date->isPast() ? 'b-danger' : 'b-muted', $due->due_date->isPast() ? 'Overdue' : 'To pay'];
    };
@endphp
<main class="main">
    <div style="display: flex; flex-direction: column; gap: 8px;">
        <span class="eyebrow">Admission application {{ now()->year }}</span>
        <h1 class="title">Pay your fees</h1>
        <p class="lead">Pay each fee in full or in parts. You’ll get a receipt for every confirmed payment.</p>
    </div>

    @include('portal.partials.steps', ['current' => 'payment', 'student' => $student])

    @if($student->status === 'draft')
        <div class="notice info" role="status">
            <span>You can pay now. Remember to also finish your details and documents and <b>submit your application</b>.</span>
            <a href="{{ route('portal.documents') }}" class="btn btn-secondary btn-sm">Go to documents</a>
        </div>
    @endif
        <section class="card flush">
            <div class="row-between" style="padding: 22px 28px; border-bottom: 1px solid var(--line-2);">
                <h2>Your fees</h2>
                <span style="color: var(--ink-2);">Still to pay <b class="mono" style="font-size: 20px; color: var(--ink);">{{ money_inr($outstanding, false) }}</b></span>
            </div>
            @php $splitDues = $dues->whereNotNull('period_no')->isNotEmpty(); $lastPeriod = -1; @endphp
            @forelse($dues as $due)
                @php [$cls, $label] = $dueBadge($due); @endphp
                @if($splitDues && (int) $due->period_no !== $lastPeriod)
                    @php $lastPeriod = (int) $due->period_no; @endphp
                    <div style="padding: 10px 28px; background: var(--line-2, #F3F4F6); font-weight: 600; font-size: 14px; color: var(--ink-2);">{{ $lastPeriod ? $due->period_name : 'One-time fees' }}</div>
                @endif
                <div class="feerow">
                    <span style="display: flex; flex-direction: column;">
                        <span style="font-weight: 600;">{{ $due->fee_head }}</span>
                        <span class="muted" style="font-size: 14px;">Due {{ $due->due_date->format('d M Y') }}@if($due->amount_paid > 0 && !$due->isSettled()) · {{ money_inr($due->amount_paid, false) }} paid @endif</span>
                    </span>
                    <span class="mono">{{ $due->isSettled() ? money_inr($due->amount_due, false) : money_inr($due->balance, false) . ($due->amount_paid > 0 ? ' left' : '') }}</span>
                    <span class="badge {{ $cls }}">{{ $label }}</span>
                </div>
            @empty
                <p class="muted" style="padding: 22px 28px; margin: 0;">No fees have been added to your application yet. The Accounts office will add them.</p>
            @endforelse
        </section>

        @if($payableDues->isNotEmpty())
            <section class="card">
                <h2>Make a payment</h2>
                <form wire:submit.prevent="pay" style="display: flex; flex-direction: column; gap: 20px;">
                    <div class="grid">
                        @include('portal.partials.field', ['name' => 'due_id', 'label' => 'Fee', 'type' => 'select', 'required' => true, 'live' => true,
                            'options' => $payableDues->mapWithKeys(fn ($d) => [$d->id => $d->fee_head . ' — ' . money_inr($d->payableBalance(), false) . ' left'])])
                        @include('portal.partials.field', ['name' => 'amount', 'label' => 'Amount (₹)', 'required' => true, 'inputmode' => 'decimal', 'class' => 'mono', 'live' => true])
                    </div>

                    <fieldset style="margin: 0; padding: 0; border: 0; display: flex; flex-direction: column; gap: 8px;">
                        <legend style="font-size: 14px; font-weight: 500; padding: 0; margin-bottom: 8px;">How would you like to pay?</legend>
                        <div class="choices wide">
                            @foreach($upiSettings as $option)
                                <button type="button" class="choice" aria-pressed="{{ $setting && $setting->id === $option->id ? 'true' : 'false' }}" wire:click="choose({{ $option->id }})" style="padding: 16px;">
                                    <span class="choice-title">{{ $option->gateway->name }} transfer</span>
                                    <span class="choice-sub">Send to our UPI ID and share the reference.</span>
                                </button>
                            @endforeach
                            @if($officeSettings->isNotEmpty())
                                <div class="choice" style="padding: 16px; cursor: default;">
                                    <span class="choice-title">At the office</span>
                                    <span class="choice-sub">{{ $officeSettings->pluck('gateway.name')->implode(', ') }} at the Accounts desk.</span>
                                </div>
                            @endif
                        </div>
                        @error('setting_id')<span class="error">{{ $message }}</span>@enderror
                        @if($upiSettings->isEmpty())
                            <span class="muted">Online transfer is not set up for your institute yet. Please pay at the Accounts office.</span>
                        @endif
                    </fieldset>

                    @if($officeSettings->contains(fn ($s) => filled($s->instructions)))
                        <div style="padding: 16px 20px; border-radius: 12px; background: var(--bg); font-size: 14px;">
                            @foreach($officeSettings->filter(fn ($s) => filled($s->instructions)) as $office)
                                <div><b>{{ $office->gateway->name }}:</b> {{ $office->instructions }}</div>
                            @endforeach
                        </div>
                    @endif

                    @if($setting)
                        <div style="display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 20px; align-items: center; padding: 20px; border-radius: 12px; background: var(--bg);">
                            @if($setting->qr_code_path)
                                <img src="{{ route('portal.payment-qr', $setting->id) }}" alt="UPI QR code" style="width: 140px; height: 140px; object-fit: contain; border-radius: 8px; background: #fff; border: 1px solid var(--line);">
                            @else
                                <div style="width: 120px; height: 120px; border-radius: 8px; background: #fff; border: 1px solid var(--line); display: flex; align-items: center; justify-content: center; font-size: 12px; text-align: center;" class="muted">Use the UPI ID</div>
                            @endif
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <span class="muted" style="font-size: 14px;">Pay to UPI ID @if($setting->payee_name)· {{ $setting->payee_name }}@endif</span>
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <span class="mono" style="font-size: 18px; font-weight: 500;">{{ $setting->upi_id }}</span>
                                    <button type="button" class="btn btn-secondary btn-sm" data-copy="{{ $setting->upi_id }}">Copy</button>
                                    @if($upiLink)<a href="{{ $upiLink }}" class="btn btn-secondary btn-sm">Pay with UPI app</a>@endif
                                </div>
                                <span style="font-size: 14px; color: var(--ink-2);">Scan with GPay, PhonePe or any UPI app (or tap “Pay with UPI app” on your phone), then fill in the details below.</span>
                                @if($setting->instructions)<span class="muted" style="font-size: 14px;">{{ $setting->instructions }}</span>@endif
                            </div>
                        </div>

                        <div class="grid">
                            @include('portal.partials.field', ['name' => 'utr', 'label' => 'UTR / UPI reference number', 'required' => true, 'placeholder' => '12 digits, from your UPI app', 'class' => 'mono'])
                            @include('portal.partials.field', ['name' => 'payer_upi', 'label' => 'Your UPI ID', 'placeholder' => 'Optional, e.g. name@okaxis'])
                        </div>

                        <label class="dropzone" style="flex-direction: row; justify-content: flex-start; text-align: left; gap: 14px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0E6B55" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16 V4 M7 9 L12 4 L17 9"></path><path d="M4 16 V20 H20 V16"></path></svg>
                            <span style="display: flex; flex-direction: column;">
                                <span style="font-weight: 600;">{{ $proof ? 'Screenshot selected: ' . $proof->getClientOriginalName() : 'Upload payment screenshot *' }}</span>
                                <span class="muted" style="font-size: 13px;">Shows the amount, date and reference number</span>
                            </span>
                            <input type="file" class="visually-hidden" wire:model="proof" accept=".jpg,.jpeg,.png,.pdf">
                        </label>
                        <div wire:loading wire:target="proof" class="muted">Uploading screenshot…</div>
                        @error('proof')<span class="error">{{ $message }}</span>@enderror

                        <p style="margin: 0; font-size: 14px; color: var(--ink-2);">Our Accounts team will confirm the transfer, usually within one working day. Your receipt appears below once it’s confirmed.</p>
                        <button type="submit" class="btn btn-primary" style="align-self: flex-start;" wire:loading.attr="disabled" wire:target="pay,proof">Submit payment</button>
                    @endif
                </form>
            </section>
        @endif

        @if($payments->isNotEmpty())
            <section class="card flush">
                <h2 style="padding: 22px 28px; border-bottom: 1px solid var(--line-2);">Payments &amp; receipts</h2>
                @foreach($payments as $payment)
                    <div class="feerow">
                        <span style="display: flex; flex-direction: column;">
                            <span class="mono" style="font-weight: 500;">{{ $payment->receipt_number ?: ($payment->transaction_reference ?: 'Payment') }}</span>
                            <span class="muted" style="font-size: 14px;">{{ optional($payment->due)->fee_head }} · {{ optional($payment->gateway)->name }} · {{ optional($payment->paid_at)->format('d M') }}</span>
                            @if($payment->status === 'failed' && $payment->rejection_reason)
                                <span style="font-size: 14px; color: var(--danger);">Not confirmed: {{ $payment->rejection_reason }}</span>
                            @endif
                        </span>
                        <span class="mono">{{ money_inr($payment->amount, false) }}</span>
                        @if($payment->status === 'success')
                            <a href="{{ route('portal.receipt', $payment->id) }}" target="_blank" style="font-weight: 600;">Download</a>
                        @elseif($payment->status === 'pending_verification')
                            <span class="badge b-info">Being confirmed</span>
                        @else
                            <span class="badge b-danger">Not confirmed</span>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

    <div class="row-between">
        <a href="{{ route('portal.documents') }}" class="btn btn-secondary">Back</a>
        <a href="{{ route('portal.status') }}" class="btn btn-primary">Application status</a>
    </div>
</main>
