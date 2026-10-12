<?php

namespace App\Http\Livewire\Portal;

use App\Http\Livewire\Portal\Concerns\StudentPortalPage;
use App\Models\Admin\InstitutePaymentGateway;
use App\Models\Admin\StudentDue;
use App\Services\FeeService;
use App\Support\PortalProgress;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Step 4 — "Payment" (design: "Website · Step 4 — Payment").
 * Open from registration onwards (fees are added when the student registers) until everything is paid.
 * Students pay by GPay / UPI transfer (UTR + screenshot → Accounts confirms) or at the office.
 * Online gateways appear here once one is integrated.
 * Fees are paid in order: only the next fee is payable; when nothing is open, the next upcoming fee
 * that allows it can be paid in advance (due_id "advance-{course fee id}"). The page lists every fee
 * of the student: paid, to pay and upcoming.
 */
class PaymentPage extends Component
{
    use StudentPortalPage, WithFileUploads;

    public $due_id, $amount, $setting_id, $utr, $payer_upi, $proof;

    public function mount()
    {
        // Payment opens once the required documents are uploaded.
        if (!PortalProgress::paymentUnlocked($this->student())) {
            session()->flash('toast', ['type' => 'warning', 'message' => 'Upload all required documents first — then you can pay your fees.']);

            return redirect()->route('portal.documents');
        }

        PortalProgress::remember($this->student(), 'payment');

        // The next fee in order. Advance payment opens only when the student chooses "Pay in advance".
        $this->selectDefault();
        $this->setting_id = optional($this->upiSettings()->first())->id;
    }

    protected function selectDefault(): void
    {
        $next = $this->nextDue();
        $this->due_id = optional($next)->id;
        $this->amount = $next ? $next->payableBalance() : null;
    }

    /** The fee the student pays next (fees are paid in order). */
    protected function nextDue(): ?StudentDue
    {
        return app(FeeService::class)->nextPayableDue($this->student());
    }

    /** The upcoming fee that can be paid in advance now, if any. */
    protected function advanceItem(): ?array
    {
        return app(FeeService::class)->upcomingFees($this->student())->first(fn ($item) => $item['advance']);
    }

    /** What the selected option pays: [StudentDue|null, CourseFee|null (advance), max amount]. */
    protected function selection(): array
    {
        if (str_starts_with((string) $this->due_id, 'advance-')) {
            $item = $this->advanceItem();
            if ($item && $this->due_id === 'advance-' . $item['fee']->id) {
                return [null, $item['fee'], (float) $item['fee']->amount];
            }

            return [null, null, 0];
        }
        $next = $this->nextDue();
        if ($next && (int) $next->id === (int) $this->due_id) {
            return [$next, null, $next->payableBalance()];
        }

        return [null, null, 0];
    }

    // ------------------------------------------------------------------ confirmation pop-up

    /** Pending confirmation: advance (course fee id) | revoke (advance due id). */
    public $confirmAction = null;
    public $confirmId = null;

    public function ask($action, $id)
    {
        if (in_array($action, ['advance', 'revoke'], true)) {
            $this->confirmAction = $action;
            $this->confirmId = (int) $id;
        }
    }

    public function closeConfirm()
    {
        $this->reset(['confirmAction', 'confirmId']);
    }

    public function confirmed()
    {
        [$action, $id] = [$this->confirmAction, $this->confirmId];
        $this->closeConfirm();

        return $action === 'advance' ? $this->payAdvance($id) : ($action === 'revoke' ? $this->revokeAdvance($id) : null);
    }

    protected function confirmInfo(): ?array
    {
        if ($this->confirmAction === 'advance') {
            $item = app(FeeService::class)->upcomingFees($this->student())->first(fn ($i) => (int) $i['fee']->id === (int) $this->confirmId);

            return $item ? [
                'title' => 'Pay in advance?', 'tone' => 'brand', 'button' => 'Yes, pay in advance',
                'text' => "{$item['fee']->fee_head} for {$item['period']} (" . money_inr($item['fee']->amount) . ') is not due yet. You can pay it now; nothing is charged until you submit the payment.',
            ] : null;
        }
        if ($this->confirmAction === 'revoke') {
            $due = StudentDue::where('student_id', $this->student()->id)->find($this->confirmId);

            return $due ? [
                'title' => 'Cancel advance payment?', 'tone' => 'danger', 'button' => 'Yes, cancel advance',
                'text' => "{$due->fee_head} (" . money_inr($due->amount_due) . ') will be removed from your fees and listed as upcoming again.',
            ] : null;
        }

        return null;
    }

    /** "Pay in advance" on an upcoming fee. */
    public function payAdvance($feeId)
    {
        $this->due_id = 'advance-' . (int) $feeId;
        [, $fee, $max] = $this->selection();
        if (!$fee) {
            $this->toast('warning', 'This fee cannot be paid in advance yet. Pay the fees before it first.');
            $this->selectDefault();

            return;
        }
        $this->amount = $max;
        $this->dispatchBrowserEvent('scroll-to-payment');
    }

    /** Close the advance payment form (e.g. "Pay in advance" clicked by mistake) — nothing was charged. */
    public function cancelAdvance()
    {
        $this->reset(['utr', 'payer_upi', 'proof']);
        $this->resetValidation();
        $this->selectDefault();
        $this->toast('info', 'Advance payment cancelled. Nothing was charged.');
    }

    /** Remove an advance fee that has no payment yet (added by mistake). */
    public function revokeAdvance($dueId)
    {
        $student = $this->student();
        $due = StudentDue::where('student_id', $student->id)->where('advance', true)->find($dueId);
        if (!$due) {
            return $this->toast('warning', 'This fee cannot be cancelled.');
        }
        try {
            app(FeeService::class)->revokeAdvance($due, 'Cancelled by the student on the admissions portal');
        } catch (RuntimeException $e) {
            return $this->toast('warning', $e->getMessage());
        }
        $this->selectDefault();
        $this->toast('success', "{$due->fee_head} removed. It is listed as upcoming again.");
    }

    /**
     * UPI methods the student's institute accepts (enabled + active in Master Data + has a UPI ID).
     */
    protected function upiSettings()
    {
        return InstitutePaymentGateway::enabled()
            ->with('gateway')
            ->where('institute_id', $this->student()->institute_id)
            ->whereNotNull('upi_id')
            ->whereHas('gateway', fn ($g) => $g->where('type', 'upi'))
            ->get();
    }

    public function updatedDueId()
    {
        [, , $max] = $this->selection();
        $this->amount = $max ?: null;
    }

    public function choose($settingId)
    {
        $this->setting_id = optional($this->upiSettings()->firstWhere('id', (int) $settingId))->id;
    }

    public function pay()
    {
        $student = $this->student();
        abort_unless(PortalProgress::paymentUnlocked($student), 403);
        abort_unless(in_array($student->status, ['draft', 'pending_docs', 'pending_approval', 'rejected', 'er_issued', 'active'], true), 403);

        [$due, $advanceFee, $max] = $this->selection();
        $setting = $this->upiSettings()->firstWhere('id', (int) $this->setting_id);

        $this->validate([
            // only the next fee in order (or the next advance fee) can be paid
            'due_id' => ['required', fn ($attr, $value, $fail) => ($due || $advanceFee) ? null : $fail('This fee cannot be paid yet. Pay the fees before it first.')],
            'setting_id' => ['required', fn ($attr, $value, $fail) => $setting ? null : $fail('Choose how you paid.')],
            'amount' => ['required', 'numeric', 'min:1', 'max:' . $max],
            'utr' => ['required', 'regex:/^[A-Za-z0-9]{6,30}$/'],
            'payer_upi' => ['nullable', 'string', 'max:100'],
            'proof' => ['required', 'file', 'mimes:' . config('camp.payment_proof_mimes'), 'max:' . config('camp.payment_proof_max_kb')],
        ], [
            'amount.max' => 'You can pay up to ' . money_inr($max) . ' for this fee.',
            'utr.required' => 'Enter the UTR / UPI reference number from your UPI app.',
            'utr.regex' => 'The reference has only letters and digits (usually 12 digits).',
            'proof.required' => 'Upload a screenshot of the payment.',
        ], ['due_id' => 'fee', 'utr' => 'UTR / reference', 'payer_upi' => 'your UPI ID', 'proof' => 'payment screenshot']);

        $fees = app(FeeService::class);
        try {
            DB::transaction(function () use ($fees, $student, $due, $advanceFee, $setting) {
                // advance: the upcoming fee is charged now, then paid like any other fee
                $due = $due ?: $fees->chargeAdvance($student, $advanceFee);
                $fees->recordPayment($due, $setting->gateway, (float) $this->amount, now()->toDateString(), $this->utr, $this->payer_upi, $this->proof,
                    $advanceFee ? 'Advance payment submitted by the student on the admissions portal' : 'Submitted by the student on the admissions portal');
            });
        } catch (RuntimeException $e) {
            $this->toast('danger', $e->getMessage());

            return null;
        }

        session()->flash('toast', ['type' => 'success', 'message' => 'Payment submitted. Accounts will confirm it, usually within one working day. Your receipt appears here once it is confirmed.']);

        return redirect()->route('portal.payment');
    }

    public function render()
    {
        $student = $this->student();
        $fees = app(FeeService::class);
        $dues = StudentDue::where('student_id', $student->id)->with('payments')
            ->orderByRaw('period_no IS NOT NULL, period_no')->orderBy('due_date')->get();
        $setting = $this->setting_id ? $this->upiSettings()->firstWhere('id', (int) $this->setting_id) : null;
        $next = $fees->nextPayableDue($student);
        $upcoming = $fees->upcomingFees($student);
        $advance = $upcoming->first(fn ($item) => $item['advance']);
        [$selectedDue, $selectedFee] = $this->selection();

        return view('portal.payment', [
            'student' => $student,
            'dues' => $dues,
            'outstanding' => $dues->whereNotIn('status', ['cleared', 'waived'])->sum(fn ($d) => $d->balance),
            'paidTotal' => $dues->sum(fn ($d) => (float) $d->amount_paid),
            'upcoming' => $upcoming,
            'upcomingTotal' => $upcoming->sum(fn ($item) => (float) $item['fee']->amount),
            'nextDue' => $next,
            'openInOrder' => $fees->openDuesInOrder($student),
            // the advance option appears only after "Pay in advance" was chosen
            'payOptions' => collect()
                ->when($next, fn ($c) => $c->put($next->id, $next->fee_head . ' — ' . money_inr($next->payableBalance(), false) . ' left'))
                ->when(!$next && $advance && $selectedFee, fn ($c) => $c->put('advance-' . $advance['fee']->id, $advance['fee']->fee_head . ' (' . $advance['period'] . ', in advance) — ' . money_inr($advance['fee']->amount, false))),
            'revocable' => $dues->filter(fn ($d) => $fees->canRevokeAdvance($d))->pluck('id'),
            'confirmInfo' => $this->confirmInfo(),
            'payingAdvance' => (bool) $selectedFee,
            'selectedLabel' => $selectedDue ? $selectedDue->fee_head : ($selectedFee ? $selectedFee->fee_head : null),
            'upiSettings' => $this->upiSettings(),
            'officeSettings' => InstitutePaymentGateway::enabled()->with('gateway')->where('institute_id', $student->institute_id)
                ->whereHas('gateway', fn ($g) => $g->where('type', 'offline'))->get(),
            'setting' => $setting,
            'upiLink' => $setting && $this->amount ? $setting->upiLink((float) $this->amount, "Fees {$student->full_name}") : null,
            'payments' => $student->payments()->with(['due', 'gateway'])->latest()->get(),
        ])->layout('layouts.portal', ['title' => 'Pay your fees']);
    }
}
