<?php

namespace App\Services;

use App\Models\Admin\CourseFee;
use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\Student;
use App\Models\Admin\StudentDue;
use App\Models\Admin\StudentPayment;
use App\Support\SecureUpload;
use App\Traits\RecordsAuditTrail;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Module 2.3 — student dues and payments (Accounts gate data source).
 *  - dues come from the institute's fee structure (CourseFee) or are added by hand
 *  - GPay/UPI and offline payments wait for Accounts verification; approval issues a receipt
 *  - amount_paid / status of a due are always recalculated from successful payments
 * Online gateways plug in later through the payment_gateways.code driver (plan.md).
 */
class FeeService
{
    use RecordsAuditTrail;

    public function __construct(
        protected NotificationService $notifications,
        protected SerialNumberService $serials,
        protected AppNotifier $inApp
    ) {
    }

    // ------------------------------------------------------------------ dues

    /**
     * Add the institute's active fee-structure lines for the student's course that are not added yet.
     */
    public function generateDues(Student $student): int
    {
        $fees = CourseFee::active()
            ->where('institute_id', $student->institute_id)
            ->where('course_id', $student->course_id)
            ->whereNotIn('id', $student->dues()->whereNotNull('course_fee_id')->pluck('course_fee_id'))
            ->orderBy('sort_order')
            ->get();

        foreach ($fees as $fee) {
            $this->createDue($student, $fee->fee_head, (float) $fee->amount, $fee->dueDateFor($student), $fee->id);
        }

        if ($fees->isNotEmpty()) {
            $this->inApp->notify('fees_added', $student, [
                'count' => $fees->count() . ' ' . ($fees->count() === 1 ? 'fee was' : 'fees were'),
                'amount' => money_inr($fees->sum('amount')),
            ]);
        }

        return $fees->count();
    }

    /**
     * Keep the course fees of a student in step with their course and joining date while
     * they register (portal). Fees that already have a payment (pending or confirmed) are left
     * alone; others are replaced when the course changes and re-dated when the joining date changes.
     */
    public function syncCourseDues(Student $student): void
    {
        $dues = $student->dues()->whereNotNull('course_fee_id')->with(['courseFee', 'payments'])->get();

        foreach ($dues as $due) {
            if ($due->payments->whereIn('status', ['pending_verification', 'success'])->isNotEmpty() || $due->status === 'waived') {
                continue;
            }

            if (!$due->courseFee || (int) $due->courseFee->course_id !== (int) $student->course_id) {
                $this->deleteDue($due); // fee of the previous course
                continue;
            }

            $dueDate = $due->courseFee->dueDateFor($student)->toDateString();
            if ($due->due_date->toDateString() !== $dueDate) {
                $due->update(['due_date' => $dueDate]);
            }
        }

        $this->generateDues($student->fresh());
    }

    /** Statuses whose students are charged automatically when a fee line is added / activated. */
    const CURRENT_STUDENT_EXCLUDED = ['rejected', 'alumni'];

    /**
     * A fee line was added to (or re-activated in) the structure: charge it right away to every
     * current student of that institute + course who does not have it yet (never duplicated).
     * Each student is notified. Returns how many students were charged.
     */
    public function applyFeeToStudents(CourseFee $fee): int
    {
        if (!$fee->status) {
            return 0;
        }

        $students = Student::where('institute_id', $fee->institute_id)
            ->where('course_id', $fee->course_id)
            ->whereNotIn('status', self::CURRENT_STUDENT_EXCLUDED)
            ->whereDoesntHave('dues', fn ($q) => $q->where('course_fee_id', $fee->id))
            ->get();

        foreach ($students as $student) {
            DB::transaction(fn () => $this->createDue($student, $fee->fee_head, (float) $fee->amount, $fee->dueDateFor($student), $fee->id));
            $this->inApp->notify('fees_added', $student, ['count' => "{$fee->fee_head} was", 'amount' => money_inr($fee->amount)]);
        }

        if ($students->isNotEmpty()) {
            $this->audit('apply_fee', 'course_fees', $fee, ['description' => "Fee {$fee->fee_head} (" . money_inr($fee->amount) . ") added to {$students->count()} existing student(s)"]);
        }

        return $students->count();
    }

    public function addDue(Student $student, string $feeHead, float $amount, $dueDate): StudentDue
    {
        $due = $this->createDue($student, $feeHead, $amount, Carbon::parse($dueDate));
        $this->inApp->notify('fees_added', $student, ['count' => "{$feeHead} was", 'amount' => money_inr($amount)]);

        return $due;
    }

    protected function createDue(Student $student, string $feeHead, float $amount, Carbon $dueDate, ?int $courseFeeId = null): StudentDue
    {
        $due = StudentDue::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'course_fee_id' => $courseFeeId,
            'fee_head' => $feeHead,
            'amount_due' => $amount,
            'amount_paid' => 0,
            'due_date' => $dueDate->toDateString(),
            'status' => 'pending',
        ]);

        $this->audit('create', 'student_dues', $due, ['new' => $due->only(['fee_head', 'amount_due', 'due_date']), 'description' => "Due added for {$student->full_name}: {$feeHead}"]);
        $this->feesChanged($student);

        return $due;
    }

    public function waiveDue(StudentDue $due, string $reason): void
    {
        if ($due->isSettled()) {
            throw new RuntimeException('This due is already settled.');
        }
        if ($due->payments()->where('status', 'pending_verification')->exists()) {
            throw new RuntimeException('Verify or reject the pending payments of this due first.');
        }

        $due->update(['status' => 'waived', 'remarks' => $reason]);
        $this->inApp->notify('fee_waived', $due->student, ['fee' => $due->fee_head, 'reason' => $reason]);
        $this->audit('waive', 'student_dues', $due, ['reason' => $reason, 'description' => "Waived {$due->fee_head} (" . money_inr($due->amount_due - $due->amount_paid) . ") for {$due->student->full_name}"]);
    }

    public function deleteDue(StudentDue $due): void
    {
        if ($due->payments()->whereIn('status', ['pending_verification', 'success'])->exists()) {
            throw new RuntimeException('A due with payments cannot be deleted. Waive it instead.');
        }

        $this->audit('delete', 'student_dues', $due, ['old' => $due->only(['fee_head', 'amount_due', 'due_date']), 'description' => "Deleted due {$due->fee_head} of {$due->student->full_name}"]);
        $due->delete();
    }

    /**
     * A new due after Gate 2 was approved (but before the ER number) reopens Gate 2.
     */
    protected function feesChanged(Student $student): void
    {
        $gate = $student->gate(EnrollmentApproval::FEES);

        if ($gate && $gate->status === 'approved' && !$student->er_number) {
            $gate->update(['status' => 'pending', 'approved_by' => null, 'approved_at' => null]);
            $this->audit('reopen_gate', 'enrollment', $student, ['description' => 'Gate 2 reopened: new fees added after approval']);
            $this->inApp->notify('gate2_reopened', $student, ['reason' => 'new fees added after approval']);
        }
    }

    // ------------------------------------------------------------------ payments

    /**
     * Record a payment received outside an online gateway (GPay/UPI transfer, cash, bank, cheque).
     * It waits for Accounts verification.
     */
    public function recordPayment(StudentDue $due, PaymentGateway $gateway, float $amount, $paidAt, ?string $reference, ?string $payerUpi, ?UploadedFile $proof, ?string $remarks): StudentPayment
    {
        if ($gateway->type === 'online') {
            throw new RuntimeException('Online gateway payments are confirmed by the gateway, not recorded by hand.');
        }
        if ($due->isSettled()) {
            throw new RuntimeException('This due is already settled.');
        }
        if ($amount <= 0 || $amount > $due->payableBalance()) {
            throw new RuntimeException('Amount must be more than zero and not more than ' . money_inr($due->payableBalance()) . '.');
        }

        $student = $due->student;

        $payment = StudentPayment::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'student_due_id' => $due->id,
            'payment_gateway_id' => $gateway->id,
            'amount' => $amount,
            'currency' => 'INR',
            'transaction_reference' => $reference ?: null,
            'payer_upi_id' => $payerUpi ?: null,
            'proof_file_path' => $proof ? SecureUpload::store($proof, 'students/payments') : null,
            'status' => 'pending_verification',
            'paid_at' => Carbon::parse($paidAt)->toDateString(),
            'remarks' => $remarks ?: null,
        ]);

        $this->audit('create', 'student_payments', $payment, [
            'new' => $payment->only(['amount', 'transaction_reference', 'paid_at']),
            'description' => "Payment recorded for {$student->full_name}: " . money_inr($amount) . " via {$gateway->name}",
        ]);

        $this->inApp->notify('payment_submitted', $student, ['amount' => money_inr($amount), 'fee' => $due->fee_head, 'method' => $gateway->name]);

        return $payment;
    }

    /**
     * Accounts approves → receipt number, due recalculated, student notified.
     */
    public function approvePayment(StudentPayment $payment, ?string $remarks = null): void
    {
        if ($payment->status !== 'pending_verification') {
            throw new RuntimeException('Only payments waiting for verification can be approved.');
        }

        DB::transaction(function () use ($payment, $remarks) {
            $payment->update([
                'status' => 'success',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'receipt_number' => $this->serials->next('RECEIPT', $payment->student->institute),
                'remarks' => $remarks ?: $payment->remarks,
            ]);

            if ($payment->due) {
                $this->recalculate($payment->due);
            }

            $this->audit('approve', 'student_payments', $payment, [
                'reason' => $remarks,
                'description' => "Payment approved, receipt {$payment->receipt_number} — " . money_inr($payment->amount) . " from {$payment->student->full_name}",
            ]);
        });

        $student = $payment->student;
        $this->inApp->notify('payment_approved', $student, ['amount' => money_inr($payment->amount), 'receipt' => $payment->receipt_number, 'fee' => optional($payment->due)->fee_head]);
        $this->notifications->send($student, 'payment_received', "Payment received — receipt {$payment->receipt_number}",
            "Dear {$student->first_name},\n\nWe have received " . money_inr($payment->amount)
            . ' towards ' . optional($payment->due)->fee_head . ".\nReceipt number: {$payment->receipt_number}\n\n" . optional($student->institute)->name);
    }

    public function rejectPayment(StudentPayment $payment, string $reason): void
    {
        if ($payment->status !== 'pending_verification') {
            throw new RuntimeException('Only payments waiting for verification can be rejected.');
        }

        $payment->update(['status' => 'failed', 'verified_by' => Auth::id(), 'verified_at' => now(), 'rejection_reason' => $reason]);
        $this->audit('reject', 'student_payments', $payment, [
            'reason' => $reason,
            'description' => 'Payment rejected — ' . money_inr($payment->amount) . " from {$payment->student->full_name}",
        ]);

        $student = $payment->student;
        $this->inApp->notify('payment_rejected', $student, ['amount' => money_inr($payment->amount), 'reason' => $reason]);
        $this->notifications->send($student, 'payment_rejected', 'Your payment could not be confirmed',
            "Dear {$student->first_name},\n\nYour payment of " . money_inr($payment->amount)
            . ($payment->transaction_reference ? " (ref. {$payment->transaction_reference})" : '')
            . " could not be confirmed.\nReason: {$reason}\n\n" . optional($student->institute)->name);
    }

    /**
     * amount_paid / status from successful payments. Waived dues stay waived.
     */
    public function recalculate(StudentDue $due): void
    {
        $paid = round((float) $due->payments()->where('status', 'success')->sum('amount'), 2);

        $status = $due->status === 'waived'
            ? 'waived'
            : ($paid >= (float) $due->amount_due ? 'cleared' : ($paid > 0 ? 'partial' : 'pending'));

        $due->update(['amount_paid' => $paid, 'status' => $status]);
    }
}
