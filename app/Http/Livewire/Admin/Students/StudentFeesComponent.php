<?php

namespace App\Http\Livewire\Admin\Students;

use App\Models\Admin\PaymentGateway;
use App\Models\Admin\Student;
use App\Models\Admin\StudentDue;
use App\Services\FeeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Module 2.3 — one student's fees: dues (from the fee structure or added by hand), recording
 * payments received by GPay/UPI or at the office, and receipts. Verification happens in the
 * Accounts payment queue. Design: "Website · Step 4 — Payment" (admin side of it).
 */
class StudentFeesComponent extends Component
{
    use WithFileUploads;

    // NOT named $student: the route parameter is {student}.
    public $studentId;

    // Add due
    public $due_fee_head, $due_amount, $due_date;

    // Waive due
    public $waiveDueId = null, $waiveReason = '';

    // Record payment
    public $pay_due_id, $pay_amount, $pay_gateway_id, $pay_date, $pay_reference, $pay_upi, $pay_proof, $pay_remarks;

    public function mount($student)
    {
        $this->authorizeView();
        $this->studentId = Student::findOrFail($student)->id; // institute scope → 404 for others
        $this->pay_date = now()->toDateString();
    }

    public function hydrate()
    {
        $this->authorizeView();
    }

    protected function authorizeView()
    {
        $user = Auth::user();
        abort_unless($user->can('students.view') && ($user->can('fees.manage') || $user->can('payments.collect') || $user->can('payments.verify')), 403);
    }

    protected function student(): Student
    {
        return Student::with(['course', 'institute'])->findOrFail($this->studentId);
    }

    protected function due($id): StudentDue
    {
        return StudentDue::where('student_id', $this->studentId)->findOrFail($id);
    }

    // ------------------------------------------------------------------ dues (fees.manage)

    public function generateDues()
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);

        $added = app(FeeService::class)->generateDues($this->student());
        $this->toast($added ? 'success' : 'info', $added
            ? "{$added} fee(s) added from the fee structure."
            : 'No new fees: everything in the fee structure is already added (or no fee structure is set for this course).');
    }

    public function addDue()
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);

        $this->validate([
            'due_fee_head' => 'required|string|max:150',
            'due_amount' => 'required|numeric|min:1|max:9999999',
            'due_date' => 'required|date',
        ], [], ['due_fee_head' => 'fee', 'due_amount' => 'amount', 'due_date' => 'due date']);

        app(FeeService::class)->addDue($this->student(), $this->due_fee_head, (float) $this->due_amount, $this->due_date);
        $this->reset(['due_fee_head', 'due_amount', 'due_date']);
        $this->dispatchBrowserEvent('close-fee-modal', ['id' => 'addDueModal']);
        $this->toast('success', 'Fee added.');
    }

    public function confirmWaive($id)
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);
        $this->waiveDueId = $this->due($id)->id;
        $this->waiveReason = '';
        $this->resetErrorBag();
        $this->dispatchBrowserEvent('open-fee-modal', ['id' => 'waiveModal']);
    }

    public function waive()
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);
        $this->validate(['waiveReason' => 'required|string|min:5|max:500'], [], ['waiveReason' => 'reason']);

        $this->run(fn () => app(FeeService::class)->waiveDue($this->due($this->waiveDueId), $this->waiveReason), 'Fee waived.');
        $this->dispatchBrowserEvent('close-fee-modal', ['id' => 'waiveModal']);
    }

    public function deleteDue($id)
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);
        $this->run(fn () => app(FeeService::class)->deleteDue($this->due($id)), 'Fee removed.', 'danger');
    }

    // ------------------------------------------------------------------ confirmation pop-up (like the delete modal)

    /** Pending confirmation: advance (course fee id) | revoke (advance due id) | delete (due id). */
    public $confirmAction = null;
    public $confirmId = null;

    const CONFIRM_ACTIONS = ['advance', 'revoke', 'delete'];

    public function ask($action, $id)
    {
        if (!in_array($action, self::CONFIRM_ACTIONS, true)) {
            return;
        }
        $this->confirmAction = $action;
        $this->confirmId = (int) $id;
        $this->dispatchBrowserEvent('open-fee-modal', ['id' => 'feeConfirmModal']);
    }

    public function confirmed()
    {
        [$action, $id] = [$this->confirmAction, $this->confirmId];
        $this->reset(['confirmAction', 'confirmId']);
        $this->dispatchBrowserEvent('close-fee-modal', ['id' => 'feeConfirmModal']);

        match ($action) {
            'advance' => $this->chargeAdvance($id),
            'revoke' => $this->revokeAdvance($id),
            'delete' => $this->deleteDue($id),
            default => null,
        };
    }

    /** Title, message, icon, colour and button of the confirmation pop-up. */
    protected function confirmInfo(): ?array
    {
        if (!$this->confirmAction || !$this->confirmId) {
            return null;
        }
        if ($this->confirmAction === 'advance') {
            $fee = \App\Models\Admin\CourseFee::find($this->confirmId);

            return $fee ? [
                'title' => 'Pay in advance?', 'icon' => 'ti ti-player-track-next', 'tone' => 'primary', 'button' => 'Yes, add as advance',
                'text' => "{$fee->fee_head} (" . money_inr($fee->amount) . ') is not due yet. It will be added to this student now so the payment can be recorded. You can cancel it while no payment is recorded.',
            ] : null;
        }
        $due = StudentDue::where('student_id', $this->studentId)->find($this->confirmId);
        if (!$due) {
            return null;
        }

        return $this->confirmAction === 'revoke'
            ? ['title' => 'Cancel advance?', 'icon' => 'ti ti-arrow-back-up', 'tone' => 'warning', 'button' => 'Yes, cancel advance',
                'text' => "{$due->fee_head} (" . money_inr($due->amount_due) . ') will be removed from this student and listed as upcoming again.']
            : ['title' => 'Remove fee?', 'icon' => 'ti ti-alert-triangle', 'tone' => 'danger', 'button' => 'Yes, remove',
                'text' => "{$due->fee_head} (" . money_inr($due->amount_due) . ') will be removed from this student. This cannot be undone.'];
    }

    /**
     * Advance payment at the desk: charge the next upcoming fee that allows it, then select it in the
     * payment form so the payment can be recorded.
     */
    public function chargeAdvance($feeId)
    {
        abort_unless(Auth::user()->can('payments.collect') || Auth::user()->can('fees.manage'), 403);

        $fee = \App\Models\Admin\CourseFee::findOrFail($feeId); // institute-scoped
        try {
            $due = app(FeeService::class)->chargeAdvance($this->student(), $fee);
        } catch (RuntimeException $e) {
            return $this->toast('warning', $e->getMessage());
        }

        $this->pay_due_id = $due->id;
        $this->pay_amount = $due->payableBalance();
        $this->toast('success', "{$due->fee_head} added as an advance fee. Record the payment below.");
    }

    /** Undo "Pay in advance" (clicked by mistake) while the advance fee has no payment. */
    public function revokeAdvance($id)
    {
        abort_unless(Auth::user()->can('payments.collect') || Auth::user()->can('fees.manage'), 403);

        $due = $this->due($id);
        $this->run(fn () => app(FeeService::class)->revokeAdvance($due, 'Cancelled at the office'), "Advance fee {$due->fee_head} cancelled. It is listed as upcoming again.", 'success');
        if ((int) $this->pay_due_id === (int) $id && optional($due->fresh())->trashed()) {
            $this->reset(['pay_due_id', 'pay_amount']);
        }
    }

    // ------------------------------------------------------------------ payments (payments.collect)

    public function updatedPayDueId()
    {
        $due = $this->pay_due_id ? StudentDue::where('student_id', $this->studentId)->find($this->pay_due_id) : null;
        $this->pay_amount = $due ? $due->payableBalance() : null;
    }

    public function recordPayment()
    {
        abort_unless(Auth::user()->can('payments.collect'), 403);

        $gateway = $this->pay_gateway_id ? PaymentGateway::find($this->pay_gateway_id) : null;
        $type = optional($gateway)->type;
        $due = $this->pay_due_id ? StudentDue::where('student_id', $this->studentId)->find($this->pay_due_id) : null;

        $this->validate([
            'pay_due_id' => ['required', Rule::exists('student_dues', 'id')->where('student_id', $this->studentId)->whereNotIn('status', ['cleared', 'waived'])->whereNull('deleted_at')],
            'pay_gateway_id' => ['required', Rule::exists('payment_gateways', 'id')->where('status', true)->whereIn('type', ['upi', 'offline'])->whereNull('deleted_at')],
            'pay_amount' => ['required', 'numeric', 'min:1', 'max:' . ($due ? $due->payableBalance() : 0)],
            'pay_date' => ['required', 'date', 'before_or_equal:today'],
            // UTR for GPay/UPI; cheque / bank reference for offline methods other than cash
            'pay_reference' => [Rule::requiredIf($type === 'upi' || ($type === 'offline' && optional($gateway)->code !== 'cash')), 'nullable', 'string', 'max:100'],
            'pay_upi' => ['nullable', 'string', 'max:100'],
            'pay_proof' => [Rule::requiredIf($type === 'upi'), 'nullable', 'file', 'mimes:' . config('camp.payment_proof_mimes'), 'max:' . config('camp.payment_proof_max_kb')],
            'pay_remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'pay_amount.max' => 'Amount cannot be more than the balance still payable on this fee (' . money_inr($due ? $due->payableBalance() : 0) . ').',
            'pay_reference.required' => $type === 'upi' ? 'Enter the UTR / UPI reference number.' : 'Enter the cheque / transaction reference.',
            'pay_proof.required' => 'Upload the payment screenshot.',
        ], [
            'pay_due_id' => 'fee', 'pay_gateway_id' => 'method', 'pay_amount' => 'amount', 'pay_date' => 'payment date',
            'pay_reference' => 'reference', 'pay_upi' => 'payer UPI ID', 'pay_proof' => 'payment proof',
        ]);

        // Accounts (payments.verify) record confirmed payments: no separate approval. Others' wait for Accounts.
        $confirm = Auth::user()->can('payments.verify');
        $receipt = null;
        try {
            $payment = app(FeeService::class)->recordPayment($due, $gateway, (float) $this->pay_amount, $this->pay_date,
                $this->pay_reference, $gateway->type === 'upi' ? $this->pay_upi : null, $this->pay_proof, $this->pay_remarks, $confirm);
            $receipt = $payment->receipt_number;
        } catch (RuntimeException $e) {
            return $this->toast('danger', $e->getMessage());
        }

        $this->reset(['pay_due_id', 'pay_amount', 'pay_gateway_id', 'pay_reference', 'pay_upi', 'pay_proof', 'pay_remarks']);
        $this->pay_date = now()->toDateString();
        $this->toast('success', $confirm
            ? "Payment recorded and confirmed. Receipt {$receipt} issued and the fee is updated."
            : 'Payment recorded. It is now waiting for Accounts verification.');
    }

    protected function run(callable $action, string $message, string $type = 'success')
    {
        try {
            $action();
            $this->toast($type, $message);
        } catch (RuntimeException $e) {
            $this->toast('danger', $e->getMessage());
        }
    }

    protected function toast($type, $message)
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }

    public function render()
    {
        $student = $this->student();
        $dues = StudentDue::where('student_id', $student->id)->with('payments')
            ->orderByRaw('period_no IS NOT NULL, period_no')->orderBy('due_date')->get();
        $gateway = $this->pay_gateway_id ? PaymentGateway::find($this->pay_gateway_id) : null;
        $fees = app(FeeService::class);
        $upcoming = $fees->upcomingFees($student);

        return view('livewire.admin.students.student-fees-component', [
            'nextDue' => $fees->nextPayableDue($student),
            'confirmInfo' => $this->confirmInfo(),
            'revocable' => $dues->filter(fn ($d) => $fees->canRevokeAdvance($d))->pluck('id'),
            'upcoming' => $upcoming,
            'upcomingTotal' => $upcoming->sum(fn ($item) => (float) $item['fee']->amount),
            'paidTotal' => $dues->sum(fn ($d) => (float) $d->amount_paid),
            'student' => $student,
            'dues' => $dues,
            'payments' => $student->payments()->with(['due', 'gateway', 'verifier'])->latest()->get(),
            'outstanding' => $dues->whereNotIn('status', ['cleared', 'waived'])->sum(fn ($d) => $d->balance),
            'gateways' => PaymentGateway::active()->whereIn('type', ['upi', 'offline'])->orderBy('sort_order')->get(),
            'selectedGateway' => $gateway,
            'payableDues' => $dues->whereNotIn('status', ['cleared', 'waived'])->filter(fn ($d) => $d->payableBalance() > 0),
        ])->layout('layouts.admin.master');
    }
}
