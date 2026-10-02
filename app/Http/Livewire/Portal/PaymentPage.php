<?php

namespace App\Http\Livewire\Portal;

use App\Http\Livewire\Portal\Concerns\StudentPortalPage;
use App\Models\Admin\InstitutePaymentGateway;
use App\Models\Admin\StudentDue;
use App\Services\FeeService;
use App\Support\PortalProgress;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Step 4 — "Payment" (design: "Website · Step 4 — Payment").
 * Open from registration onwards (fees are added when the student registers) until everything is paid.
 * Students pay by GPay / UPI transfer (UTR + screenshot → Accounts confirms) or at the office.
 * Online gateways appear here once one is integrated.
 */
class PaymentPage extends Component
{
    use StudentPortalPage, WithFileUploads;

    public $due_id, $amount, $setting_id, $utr, $payer_upi, $proof;

    public function mount()
    {
        PortalProgress::remember($this->student(), 'payment');

        // "Pay now" links pass the fee to pay (?due=); otherwise the first payable fee.
        $payable = $this->payableDues();
        $first = $payable->firstWhere('id', (int) request()->query('due')) ?? $payable->first();
        $this->due_id = optional($first)->id;
        $this->amount = $first ? $first->payableBalance() : null;
        $this->setting_id = optional($this->upiSettings()->first())->id;
    }

    protected function payableDues()
    {
        return StudentDue::where('student_id', $this->student()->id)
            ->whereNotIn('status', ['cleared', 'waived'])
            ->orderBy('due_date')->get()
            ->filter(fn ($due) => $due->payableBalance() > 0)
            ->values();
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
        $due = $this->due_id ? StudentDue::where('student_id', $this->student()->id)->find($this->due_id) : null;
        $this->amount = $due ? $due->payableBalance() : null;
    }

    public function choose($settingId)
    {
        $this->setting_id = optional($this->upiSettings()->firstWhere('id', (int) $settingId))->id;
    }

    public function pay()
    {
        $student = $this->student();
        abort_unless(in_array($student->status, ['draft', 'pending_docs', 'pending_approval', 'rejected', 'er_issued', 'active'], true), 403);

        $due = $this->due_id ? StudentDue::where('student_id', $student->id)->find($this->due_id) : null;
        $setting = $this->upiSettings()->firstWhere('id', (int) $this->setting_id);

        $this->validate([
            'due_id' => ['required', Rule::exists('student_dues', 'id')->where('student_id', $student->id)->whereNotIn('status', ['cleared', 'waived'])->whereNull('deleted_at')],
            'setting_id' => ['required', fn ($attr, $value, $fail) => $setting ? null : $fail('Choose how you paid.')],
            'amount' => ['required', 'numeric', 'min:1', 'max:' . ($due ? $due->payableBalance() : 0)],
            'utr' => ['required', 'regex:/^[A-Za-z0-9]{6,30}$/'],
            'payer_upi' => ['nullable', 'string', 'max:100'],
            'proof' => ['required', 'file', 'mimes:' . config('camp.payment_proof_mimes'), 'max:' . config('camp.payment_proof_max_kb')],
        ], [
            'amount.max' => 'You can pay up to ' . money_inr($due ? $due->payableBalance() : 0) . ' for this fee.',
            'utr.required' => 'Enter the UTR / UPI reference number from your UPI app.',
            'utr.regex' => 'The reference has only letters and digits (usually 12 digits).',
            'proof.required' => 'Upload a screenshot of the payment.',
        ], ['due_id' => 'fee', 'utr' => 'UTR / reference', 'payer_upi' => 'your UPI ID', 'proof' => 'payment screenshot']);

        try {
            app(FeeService::class)->recordPayment($due, $setting->gateway, (float) $this->amount, now()->toDateString(), $this->utr, $this->payer_upi, $this->proof, 'Submitted by the student on the admissions portal');
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
        $dues = StudentDue::where('student_id', $student->id)->with('payments')->orderBy('due_date')->get();
        $setting = $this->setting_id ? $this->upiSettings()->firstWhere('id', (int) $this->setting_id) : null;

        return view('portal.payment', [
            'student' => $student,
            'dues' => $dues,
            'outstanding' => $dues->whereNotIn('status', ['cleared', 'waived'])->sum(fn ($d) => $d->balance),
            'payableDues' => $this->payableDues(),
            'upiSettings' => $this->upiSettings(),
            'officeSettings' => InstitutePaymentGateway::enabled()->with('gateway')->where('institute_id', $student->institute_id)
                ->whereHas('gateway', fn ($g) => $g->where('type', 'offline'))->get(),
            'setting' => $setting,
            'upiLink' => $setting && $this->amount ? $setting->upiLink((float) $this->amount, "Fees {$student->full_name}") : null,
            'payments' => $student->payments()->with(['due', 'gateway'])->latest()->get(),
        ])->layout('layouts.portal', ['title' => 'Pay your fees']);
    }
}
