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

        $this->run(function () use ($due, $gateway) {
            app(FeeService::class)->recordPayment($due, $gateway, (float) $this->pay_amount, $this->pay_date,
                $this->pay_reference, $gateway->type === 'upi' ? $this->pay_upi : null, $this->pay_proof, $this->pay_remarks);
            $this->reset(['pay_due_id', 'pay_amount', 'pay_gateway_id', 'pay_reference', 'pay_upi', 'pay_proof', 'pay_remarks']);
            $this->pay_date = now()->toDateString();
        }, 'Payment recorded. It is now waiting for Accounts verification.');
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
        $dues = StudentDue::where('student_id', $student->id)->with('payments')->orderBy('due_date')->get();
        $gateway = $this->pay_gateway_id ? PaymentGateway::find($this->pay_gateway_id) : null;

        return view('livewire.admin.students.student-fees-component', [
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
