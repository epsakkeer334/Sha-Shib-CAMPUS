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
 * Status cards: All payments / To verify / Approved / Rejected / Fee gate. Nothing is hidden once
 * handled: "All" lists every payment (those waiting for verification first and highlighted), and the
 * Fee gate lists every student past Gate 1 (those waiting for Accounts first, approved ones kept).
 */
class PaymentVerificationComponent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    const PER_PAGE = 15;

    const STATUS_BY_TAB = ['pending' => 'pending_verification', 'approved' => 'success', 'rejected' => 'failed'];

    public $tab = 'all';
    public $search = '';
    public $method = '';
    public $selectedPaymentId = null;
    public $reason = '';

    protected $queryString = ['tab' => ['except' => 'all'], 'search' => ['except' => ''], 'method' => ['except' => '']];

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
            if ($property === 'tab') {
                $this->selectedPaymentId = null;
            }
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
        $query = StudentPayment::with(['student.course', 'student.approvals', 'due', 'gateway']);

        if (isset(self::STATUS_BY_TAB[$this->tab])) {
            $query->where('status', self::STATUS_BY_TAB[$this->tab]);
        }
        if ($this->method) {
            $query->where('payment_gateway_id', $this->method);
        }
        if ($term = trim($this->search)) {
            $query->where(fn ($q) => $q->where('transaction_reference', 'like', "%{$term}%")
                ->orWhere('receipt_number', 'like', "%{$term}%")
                ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")->orWhere('er_number', 'like', "%{$term}%")));
        }

        // Waiting for verification first (oldest first — first come, first served), then the rest newest first
        return $query
            ->orderByRaw("CASE WHEN status = 'pending_verification' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'pending_verification' THEN created_at END ASC")
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Gate 2 list: every student whose documents gate is approved. Those still waiting for Accounts
     * come first; students already approved stay listed (not hidden).
     */
    protected function feeGateStudents()
    {
        $query = Student::with(['course', 'dues', 'payments', 'approvals.approver'])
            ->whereHas('approvals', fn ($q) => $q->where('gate', EnrollmentApproval::DOCUMENTS)->where('status', 'approved'))
            ->whereHas('approvals', fn ($q) => $q->where('gate', EnrollmentApproval::FEES));

        if ($term = trim($this->search)) {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('er_number', 'like', "%{$term}%"));
        }

        return $query
            ->orderByRaw(
                "CASE WHEN EXISTS (SELECT 1 FROM enrollment_approvals a WHERE a.student_id = students.id AND a.gate = ? AND a.status = 'pending' AND a.deleted_at IS NULL) THEN 0 ELSE 1 END",
                [EnrollmentApproval::FEES]
            )
            ->orderBy('onboarding_deadline')
            ->paginate(self::PER_PAGE);
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
            ? StudentPayment::with(['student.course', 'student.approvals.approver', 'student.dues', 'due', 'gateway', 'recorder', 'verifier'])->find($this->selectedPaymentId)
            : null;

        $gateBase = fn () => Student::whereHas('approvals', fn ($q) => $q->where('gate', EnrollmentApproval::DOCUMENTS)->where('status', 'approved'));

        return view('livewire.admin.onboarding.payment-verification-component', [
            'payments' => $this->tab === 'gate' ? null : $this->payments(),
            'gateStudents' => $this->tab === 'gate' ? $this->feeGateStudents() : null,
            'counts' => [
                'all' => StudentPayment::count(),
                'pending' => StudentPayment::where('status', 'pending_verification')->count(),
                'pendingAmount' => (float) StudentPayment::where('status', 'pending_verification')->sum('amount'),
                'approved' => StudentPayment::where('status', 'success')->count(),
                'approvedAmount' => (float) StudentPayment::where('status', 'success')->sum('amount'),
                'rejected' => StudentPayment::where('status', 'failed')->count(),
                'gate' => $gateBase()->whereHas('approvals', fn ($q) => $q->where('gate', EnrollmentApproval::FEES)->where('status', 'pending'))->count(),
            ],
            'methods' => PaymentGateway::orderBy('sort_order')->pluck('name', 'id'),
            'selected' => $selected,
            'onboarding' => app(OnboardingService::class),
        ])->layout('layouts.admin.master');
    }
}
