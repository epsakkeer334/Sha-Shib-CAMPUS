@php
    $dueBadge = function ($due) {
        if ($due->status === 'cleared') return ['b-ok', 'Paid'];
        if ($due->status === 'waived') return ['b-muted', 'Waived'];
        if ($due->payments->where('status', 'pending_verification')->isNotEmpty()) return ['b-info', 'Being confirmed'];
        if ($due->status === 'partial') return ['b-warn', 'Part paid'];
        return [$due->due_date->isPast() ? 'b-danger' : 'b-muted', $due->due_date->isPast() ? 'Overdue' : 'To pay'];
    };
    // every fee of the student in one list, grouped by period: charged fees, then upcoming ones
    $groups = collect();
    foreach ($dues as $due) {
        $key = (int) $due->period_no;
        $groups[$key] = ($groups[$key] ?? collect([
            'label' => $key ? $due->period_name : 'One-time fees', 'items' => collect(),
        ]));
        $groups[$key]['items']->push(['type' => 'due', 'due' => $due]);
    }
    foreach ($upcoming as $item) {
        $key = (int) $item['period_no'];
        $groups[$key] = ($groups[$key] ?? collect(['label' => $item['period'], 'items' => collect()]));
        $groups[$key]['items']->push(['type' => 'upcoming'] + $item);
    }
    $groups = $groups->sortKeys();
    $openIds = $openInOrder->pluck('id');
    $grandTotal = $dues->where('status', '!=', 'waived')->sum(fn ($d) => (float) $d->amount_due) + $upcomingTotal;
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
        {{-- Every fee assigned to the student: paid, to pay and upcoming --}}
        <section class="card flush">
            <div class="row-between" style="padding: 22px 28px; border-bottom: 1px solid var(--line-2); flex-wrap: wrap; gap: 12px;">
                <h2>Your fees</h2>
                <span class="muted" style="font-size: 14px;">Fees are paid in order. The next one opens once the one before it is paid.</span>
            </div>
            <div class="fee-totals">
                <div><span>Paid</span><b class="mono">{{ money_inr($paidTotal, false) }}</b></div>
                <div class="is-due"><span>To pay now</span><b class="mono">{{ money_inr($outstanding, false) }}</b></div>
                <div><span>Upcoming</span><b class="mono">{{ money_inr($upcomingTotal, false) }}</b></div>
                <div><span>All fees</span><b class="mono">{{ money_inr($grandTotal, false) }}</b></div>
            </div>
            @forelse($groups as $key => $group)
                <div class="fee-group">{{ $group['label'] }}</div>
                @foreach($group['items'] as $row)
                    @if($row['type'] === 'due')
                        @php
                            $due = $row['due'];
                            [$cls, $label] = $dueBadge($due);
                            $isNext = $nextDue && $nextDue->id === $due->id;
                            $waiting = !$isNext && $openIds->contains($due->id) && $due->payableBalance() > 0;
                            if ($waiting) { [$cls, $label] = ['b-muted', 'Waiting']; }
                        @endphp
                        <div class="feerow {{ $isNext ? 'fee-next' : '' }}">
                            <span style="display: flex; flex-direction: column;">
                                <span style="font-weight: 600;">{{ $due->fee_head }}
                                    @if($due->advance)<span class="fee-tag">{{ $due->status === 'cleared' ? 'Paid in advance' : 'Advance' }}</span>@endif
                                </span>
                                <span class="muted" style="font-size: 14px;">
                                    Due {{ $due->due_date->format('d M Y') }}@if($due->amount_paid > 0 && !$due->isSettled()) · {{ money_inr($due->amount_paid, false) }} paid @endif
                                    @if($waiting) · opens after the fee before it is paid @endif
                                </span>
                            </span>
                            <span class="mono">{{ $due->isSettled() ? money_inr($due->amount_due, false) : money_inr($due->balance, false) . ($due->amount_paid > 0 ? ' left' : '') }}</span>
                            @if($revocable->contains($due->id))
                                <span style="display: inline-flex; gap: 8px; align-items: center;">
                                    @if($isNext)<a href="#make-payment" class="badge b-warn" style="text-decoration: none;">Pay now →</a>@endif
                                    <button type="button" class="link-btn" style="font-size: 14px;" wire:click="ask('revoke', {{ $due->id }})">Cancel advance</button>
                                </span>
                            @elseif($isNext)
                                <a href="#make-payment" class="badge b-warn" style="text-decoration: none;">Pay now →</a>
                            @else
                                <span class="badge {{ $cls }}">{{ $label }}</span>
                            @endif
                        </div>
                    @else
                        <div class="feerow fee-upcoming">
                            <span style="display: flex; flex-direction: column;">
                                <span style="font-weight: 600;">{{ $row['fee']->fee_head }}
                                    @if($row['advance_allowed'])<span class="fee-tag fee-tag-adv">Advance allowed</span>@endif
                                </span>
                                <span class="muted" style="font-size: 14px;">{{ $row['when'] }}@if($row['expected']) · expected {{ $row['expected']->format('d M Y') }}@endif</span>
                            </span>
                            <span class="mono">{{ money_inr($row['fee']->amount, false) }}</span>
                            @if($row['advance'])
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="ask('advance', {{ $row['fee']->id }})">Pay in advance</button>
                            @else
                                <span class="badge b-info">Upcoming</span>
                            @endif
                        </div>
                    @endif
                @endforeach
            @empty
                <p class="muted" style="padding: 22px 28px; margin: 0;">No fees have been added to your application yet. The Accounts office will add them.</p>
            @endforelse
        </section>

        @if($payOptions->isNotEmpty())
            <section class="card" id="make-payment">
                <h2>{{ $payingAdvance ? 'Pay in advance' : 'Make a payment' }}</h2>
                @if($payingAdvance)
                    <div class="notice info" role="status" style="margin: 0; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <span><b>{{ $selectedLabel }}</b> is not due yet. Paying it now adds it to your fees and marks it as paid in advance. Nothing is charged until you submit the payment.</span>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelAdvance">Cancel</button>
                    </div>
                @endif
                <form wire:submit.prevent="pay" style="display: flex; flex-direction: column; gap: 20px;">
                    <div class="grid">
                        @include('portal.partials.field', ['name' => 'due_id', 'label' => 'Fee', 'type' => 'select', 'required' => true, 'live' => true,
                            'options' => $payOptions->all()])
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

    {{-- Confirmation pop-up (pay in advance / cancel advance) --}}
    @if($confirmInfo)
        <div class="p-modal-backdrop" wire:click.self="closeConfirm" role="dialog" aria-modal="true" aria-labelledby="pModalTitle">
            <div class="p-modal">
                <span class="p-modal-icon p-modal-{{ $confirmInfo['tone'] }}" aria-hidden="true">
                    @if($confirmInfo['tone'] === 'danger')
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"></path><path d="M4 9h11a5 5 0 0 1 0 10h-1"></path></svg>
                    @else
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m13 6 6 6-6 6"></path></svg>
                    @endif
                </span>
                <h3 id="pModalTitle">{{ $confirmInfo['title'] }}</h3>
                <p>{{ $confirmInfo['text'] }}</p>
                <div class="p-modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="closeConfirm">Cancel</button>
                    <button type="button" class="btn {{ $confirmInfo['tone'] === 'danger' ? 'btn-danger' : 'btn-primary' }}" wire:click="confirmed" wire:loading.attr="disabled">{{ $confirmInfo['button'] }}</button>
                </div>
            </div>
        </div>
    @endif

    <style>
        .p-modal-backdrop { position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(23, 32, 27, .55); animation: pFade .15s ease-out; }
        .p-modal { width: 100%; max-width: 440px; background: var(--surface); border-radius: 16px; padding: 28px 28px 24px; text-align: center; box-shadow: 0 24px 60px rgba(0, 0, 0, .25); animation: pPop .18s ease-out; }
        .p-modal h3 { margin: 14px 0 8px; font-size: 20px; font-weight: 600; color: var(--ink); }
        .p-modal p { margin: 0 0 22px; color: var(--ink-2); font-size: 15px; line-height: 1.5; }
        .p-modal-icon { width: 56px; height: 56px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
        .p-modal-brand { background: var(--brand-soft); color: var(--brand); }
        .p-modal-danger { background: var(--danger-bg); color: var(--danger); }
        .p-modal-actions { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
        .p-modal-actions .btn { min-width: 120px; }
        @keyframes pFade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes pPop { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: none; } }
        .fee-totals { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-bottom: 1px solid var(--line-2); }
        .fee-totals > div { display: flex; flex-direction: column; gap: 2px; padding: 14px 28px; border-right: 1px solid var(--line-2); }
        .fee-totals > div:last-child { border-right: 0; }
        .fee-totals span { font-size: 13px; color: var(--muted); }
        .fee-totals b { font-size: 18px; color: var(--ink); }
        .fee-totals .is-due b { color: var(--warn); }
        .fee-group { padding: 10px 28px; background: var(--line-2); font-weight: 600; font-size: 14px; color: var(--ink-2); }
        .fee-next { background: var(--brand-tint); box-shadow: inset 4px 0 0 var(--brand); }
        .fee-upcoming { background: #FBFBF9; }
        .fee-upcoming .mono { color: var(--muted); }
        .fee-tag { display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 999px; background: var(--brand-soft); color: var(--brand-ink); font-size: 12px; font-weight: 600; vertical-align: middle; }
        .fee-tag-adv { background: var(--info-bg); color: var(--info); }
        @media (max-width: 760px) {
            .fee-totals { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .fee-totals > div { padding: 12px 18px; border-bottom: 1px solid var(--line-2); }
            .fee-group { padding: 10px 18px; }
        }
    </style>
    <script>
        window.addEventListener('scroll-to-payment', () => setTimeout(() => {
            const el = document.getElementById('make-payment');
            if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        }, 150));
    </script>

    <div class="row-between">
        <a href="{{ route('portal.documents') }}" class="btn btn-secondary">Back</a>
        <a href="{{ route('portal.status') }}" class="btn btn-primary">Application status</a>
    </div>
</main>
