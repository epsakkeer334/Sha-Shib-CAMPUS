<?php

namespace App\Http\Livewire\Admin\Onboarding;

use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\Student;
use App\Models\Admin\StudentPayment;
use App\Services\FeeService;
use App\Services\OnboardingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Module 2.3 — Accounts payment verification & fee gate (Gate 2).
 * Design: "Accounts · Payment verification & fee gate".
 * Tabs: To verify / Approved / Rejected / Fee gate. Payment detail panel on the right.
 */
class PaymentVerificationComponent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $tab = 'pending';
    public $search = '';
    public $method = '';
    public $selectedPaymentId = null;
    public $reason = '';

    protected $queryString = ['tab' => ['except' => 'pending']];

    public function mount()
    {
        abort_unless(Auth::user()->can('payments.verify'), 403);
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('payments.verify'), 403);
    }

    public function updated($property)
    {
        if (in_array($property, ['tab', 'search', 'method'], true)) {
            $this->resetPage();
            $this->selectedPaymentId = null;
            $this->reason = '';
            $this->resetErrorBag();
        }
    }

    public function select($id)
    {
        $this->selectedPaymentId = $id;
        $this->reason = '';
        $this->resetErrorBag();
    }

    protected function payments()
    {
        $status = ['pending' => 'pending_verification', 'approved' => 'success', 'rejected' => 'failed'][$this->tab] ?? 'pending_verification';

        $query = StudentPayment::with(['student.course', 'due', 'gateway'])->where('status', $status);

        if ($this->method) {
            $query->where('payment_gateway_id', $this->method);
        }
        if ($term = trim($this->search)) {
            $query->where(fn ($q) => $q->where('transaction_reference', 'like', "%{$term}%")
                ->orWhere('receipt_number', 'like', "%{$term}%")
                ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")));
        }

        return $query->orderBy($status === 'pending_verification' ? 'created_at' : 'verified_at', $status === 'pending_verification' ? 'asc' : 'desc')->paginate(15);
    }

    /**
     * Gate 2 queue: Gate 1 approved, Gate 2 not approved yet.
     */
    protected function feeGateStudents()
    {
        $query = Student::with(['course', 'dues', 'payments'])
            ->whereHas('approvals', fn ($q) => $q->where('gate', EnrollmentApproval::DOCUMENTS)->where('status', 'approved'))
            ->whereHas('approvals', fn ($q) => $q->where('gate', EnrollmentApproval::FEES)->where('status', 'pending'));

        if ($term = trim($this->search)) {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%"));
        }

        return $query->orderBy('onboarding_deadline')->paginate(15);
    }

    public function approve()
    {
        $payment = $this->selectedPayment();
        $this->run(fn () => app(FeeService::class)->approvePayment($payment, $this->reason ?: null), 'Payment approved and receipt issued.');
    }

    public function reject()
    {
        $this->validate(['reason' => 'required|string|min:5|max:500']);
        $payment = $this->selectedPayment();
        $this->run(fn () => app(FeeService::class)->rejectPayment($payment, $this->reason), 'Payment rejected — the student has been notified.', 'warning');
    }

    public function approveGate($studentId)
    {
        $student = Student::findOrFail($studentId); // institute-scoped
        $this->run(fn () => app(OnboardingService::class)->approveGate($student, EnrollmentApproval::FEES),
            "Gate 2 approved for {$student->full_name}.");
    }

    protected function selectedPayment(): StudentPayment
    {
        abort_unless($this->selectedPaymentId, 404);

        return StudentPayment::with(['student', 'due'])->findOrFail($this->selectedPaymentId);
    }

    protected function run(callable $action, string $message, string $type = 'success')
    {
        try {
            $action();
            $this->reason = '';
            $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
        } catch (RuntimeException $e) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'danger', 'message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        $selected = $this->selectedPaymentId
            ? StudentPayment::with(['student.course', 'due', 'gateway', 'recorder', 'verifier'])->find($this->selectedPaymentId)
            : null;

        return view('livewire.admin.onboarding.payment-verification-component', [
            'payments' => $this->tab === 'gate' ? null : $this->payments(),
            'gateStudents' => $this->tab === 'gate' ? $this->feeGateStudents() : null,
            'pendingCount' => StudentPayment::where('status', 'pending_verification')->count(),
            'gateCount' => Student::whereHas('approvals', fn ($q) => $q->where('gate', EnrollmentApproval::DOCUMENTS)->where('status', 'approved'))
                ->whereHas('approvals', fn ($q) => $q->where('gate', EnrollmentApproval::FEES)->where('status', 'pending'))->count(),
            'methods' => PaymentGateway::orderBy('sort_order')->pluck('name', 'id'),
            'selected' => $selected,
            'onboarding' => app(OnboardingService::class),
        ])->layout('layouts.admin.master');
    }
}
