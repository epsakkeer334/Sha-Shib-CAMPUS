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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Module 2.3 — student dues and payments (Accounts gate data source).
 *  - dues come from the institute's fee structure (CourseFee) or are added by hand
 *  - GPay/UPI and offline payments wait for Accounts verification; approval issues a receipt
 *  - amount_paid / status of a due are always recalculated from successful payments
 * Online gateways plug in later through the payment_gateways.code driver (plan.md).
 * Fee schedule: fees are paid in order (nextPayableDue); fees not charged yet are listed as upcoming
 * (upcomingFees) and, when the fee line allows it, can be paid in advance (chargeAdvance).
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
     * Does this fee line apply to the student right now?
     *  - during registration (no ER number) and the course has an admission fee → only the admission fee
     *  - one-time line (no period): its academic year is empty or the student's intake year
     *  - period line: the student's current period (1 until promotion — Module 5), and its academic year is
     *    empty or the academic year that period runs in
     */
    public function appliesTo(CourseFee $fee, Student $student): bool
    {
        if (!$fee->status || (int) $fee->institute_id !== (int) $student->institute_id || (int) $fee->course_id !== (int) $student->course_id) {
            return false;
        }
        if (!$student->er_number && CourseFee::courseHasAdmissionFee((int) $student->institute_id, (int) $student->course_id)) {
            return (bool) $fee->admission_fee;
        }

        $periods = app(PeriodService::class);
        if ($fee->isOneTime()) {
            return !$fee->academic_year_id || (int) $fee->academic_year_id === (int) $periods->intakeAcademicYearId($student);
        }

        return (int) $fee->period_no === $periods->currentPeriodNo($student)
            && (!$fee->academic_year_id || (int) $fee->academic_year_id === (int) $periods->currentAcademicYearId($student));
    }

    /**
     * Add the course's fee lines that apply to the student now (see appliesTo) and they do not have yet.
     *  - registration: the admission fee (or, for a course without one, the one-time + period 1 lines)
     *  - ER number issued: one-time lines + period 1 lines
     *  - promotion to period N (Module 5): period N lines
     */
    public function generateDues(Student $student): int
    {
        $fees = CourseFee::active()
            ->where('institute_id', $student->institute_id)
            ->where('course_id', $student->course_id)
            ->whereNotIn('id', $student->dues()->whereNotNull('course_fee_id')->pluck('course_fee_id'))
            ->orderByRaw('period_no IS NOT NULL')->orderBy('period_no')->orderBy('sort_order')
            ->get()
            ->filter(fn ($fee) => $this->appliesTo($fee, $student))
            ->values();

        foreach ($fees as $fee) {
            $this->createDue($student, $fee->fee_head, (float) $fee->amount, $fee->dueDateFor($student), $fee->id, $fee);
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
     * Existing student (step 2.8, only when asked): the fee lines of their current period that they do not
     * have yet. One-time fees (admission …) are not charged — they were collected before CAMP.
     */
    public function chargeCurrentPeriodFees(Student $student): int
    {
        $fees = CourseFee::active()->where('institute_id', $student->institute_id)->where('course_id', $student->course_id)
            ->whereNotNull('period_no')
            ->whereNotIn('id', $student->dues()->whereNotNull('course_fee_id')->pluck('course_fee_id'))
            ->orderBy('sort_order')->get()
            ->filter(fn ($fee) => $this->appliesTo($fee, $student))->values();

        foreach ($fees as $fee) {
            $this->createDue($student, $fee->fee_head, (float) $fee->amount, $fee->dueDateFor($student), $fee->id, $fee);
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

            if (!$due->courseFee || (int) $due->courseFee->course_id !== (int) $student->course_id
                || (!$student->er_number && !$this->appliesTo($due->courseFee, $student))) {
                $this->deleteDue($due); // fee of the previous course / of another academic year
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

        // Charged to the current students it applies to right now (admission fee → still registering;
        // one-time / period lines → by intake year / current period — see appliesTo)
        $students = Student::where('institute_id', $fee->institute_id)
            ->where('course_id', $fee->course_id)
            ->whereNotIn('status', self::CURRENT_STUDENT_EXCLUDED)
            ->when($fee->admission_fee, fn ($q) => $q->whereNull('er_number'))
            ->whereDoesntHave('dues', fn ($q) => $q->where('course_fee_id', $fee->id))
            ->get()
            ->filter(fn ($student) => $this->appliesTo($fee, $student))
            ->values();

        foreach ($students as $student) {
            DB::transaction(fn () => $this->createDue($student, $fee->fee_head, (float) $fee->amount, $fee->dueDateFor($student), $fee->id, $fee));
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

    protected function createDue(Student $student, string $feeHead, float $amount, Carbon $dueDate, ?int $courseFeeId = null, ?CourseFee $line = null, bool $advance = false): StudentDue
    {
        $periods = app(PeriodService::class);
        $due = StudentDue::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'course_fee_id' => $courseFeeId,
            // period dues remember their period / academic year (grouping, exam dues gate)
            'period_no' => optional($line)->period_no,
            'academic_year_id' => $line && $line->period_no
                ? (optional($periods->periodFor($student, $line->period_no))->academic_year_id ?? $periods->currentAcademicYearId($student))
                : optional($line)->academic_year_id,
            'advance' => $advance,
            'fee_head' => $feeHead,
            'amount_due' => $amount,
            'amount_paid' => 0,
            'due_date' => $dueDate->toDateString(),
            'status' => 'pending',
        ]);

        $this->audit('create', 'student_dues', $due, ['new' => $due->only(['fee_head', 'amount_due', 'due_date']), 'description' => "Due added for {$student->full_name}: {$feeHead}"]);
        app(ScholarshipService::class)->applyTo($due); // active scholarships discount fees charged later (Module 2C)
        $this->feesChanged($student);

        return $due->fresh();
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

    // ------------------------------------------------------------------ fee schedule, payment order, advance

    /**
     * Open dues in the order they are paid: due date, then one-time before period fees, then the
     * order they were added.
     */
    public function openDuesInOrder(Student $student): Collection
    {
        return $student->dues()->whereIn('status', ['pending', 'partial'])->get()
            ->sortBy(fn (StudentDue $due) => $due->due_date->format('Ymd')
                . ($due->period_no === null ? '0000' : sprintf('1%03d', $due->period_no))
                . sprintf('%012d', $due->id))
            ->values();
    }

    /**
     * The fee the student pays next: the first open due (in order) not yet fully covered by payments
     * (confirmed or waiting for verification). The fee after it opens once it is covered.
     */
    public function nextPayableDue(Student $student): ?StudentDue
    {
        return $this->openDuesInOrder($student)->first(fn (StudentDue $due) => $due->payableBalance() > 0);
    }

    /**
     * Fees of the student's course that are not charged yet but will reach them later, in order:
     *  - before the ER number (course with an admission fee): the one-time and period 1 fees charged with the ER number
     *  - the fees of later periods (semesters), for the academic year each period runs in (or every year)
     * Each item: fee (CourseFee), period_no, period (label), expected (start date or null), when (text),
     * advance_allowed (the fee line allows advance payment) and advance (can be paid in advance right now:
     * the student has the ER number, no charged fee is still open, and it is the first such fee in order).
     */
    public function upcomingFees(Student $student): Collection
    {
        if (in_array($student->status, self::CURRENT_STUDENT_EXCLUDED, true) || !$student->course_id) {
            return collect();
        }

        $periods = app(PeriodService::class);
        $course = $student->course;
        $preEr = !$student->er_number && CourseFee::courseHasAdmissionFee((int) $student->institute_id, (int) $student->course_id);
        $current = $preEr ? 0 : $periods->currentPeriodNo($student);
        $intake = $periods->intakeAcademicYearId($student);
        $charged = $student->dues()->whereNotNull('course_fee_id')->pluck('course_fee_id')->all();

        $items = CourseFee::active()
            ->where('institute_id', $student->institute_id)->where('course_id', $student->course_id)
            ->whereNotIn('id', $charged)
            ->orderByRaw('period_no IS NOT NULL')->orderBy('period_no')->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(function (CourseFee $fee) use ($student, $periods, $course, $preEr, $current, $intake) {
                if ($fee->isOneTime()) {
                    // after the ER number every one-time fee that applies is already charged
                    if (!$preEr || $fee->admission_fee || ($fee->academic_year_id && (int) $fee->academic_year_id !== (int) $intake)) {
                        return null;
                    }

                    return ['fee' => $fee, 'period_no' => null, 'period' => 'One-time fees', 'expected' => null, 'when' => 'Charged with your ER number'];
                }
                if ($fee->period_no <= $current || ($course && $fee->period_no > $course->total_periods)) {
                    return null; // current / past period, or beyond the course
                }
                $period = $periods->periodFor($student, $fee->period_no);
                if ($fee->academic_year_id && (int) $fee->academic_year_id !== (int) optional($period)->academic_year_id) {
                    return null; // a fee of another academic year
                }
                $label = $course ? $course->periodName($fee->period_no) : 'Period ' . $fee->period_no;

                return [
                    'fee' => $fee, 'period_no' => $fee->period_no, 'period' => $label, 'expected' => optional($period)->start_date,
                    'when' => $preEr && $fee->period_no === 1 ? 'Charged with your ER number' : "Charged when {$label} starts",
                ];
            })
            ->filter()
            ->values();

        $canAdvance = $student->er_number && in_array($student->status, ['er_issued', 'active'], true) && !$this->nextPayableDue($student);
        $first = $canAdvance ? $items->first(fn ($item) => $item['period_no'] && $item['fee']->allow_advance) : null;

        return $items->map(fn ($item) => $item + [
            'advance_allowed' => $item['period_no'] && $item['fee']->allow_advance,
            'advance' => $first && $item['fee']->id === $first['fee']->id,
        ]);
    }

    /**
     * Charge an upcoming fee now so it can be paid in advance — only the fee that is next in order
     * (see upcomingFees). It is never charged again when its period starts.
     */
    public function chargeAdvance(Student $student, CourseFee $fee): StudentDue
    {
        $next = $this->upcomingFees($student)->first(fn ($item) => $item['advance']);
        if (!$next || (int) $next['fee']->id !== (int) $fee->id) {
            throw new RuntimeException('This fee cannot be paid in advance yet. Pay the fees before it first.');
        }

        return DB::transaction(function () use ($student, $fee) {
            $due = $this->createDue($student, $fee->fee_head, (float) $fee->amount, $fee->dueDateFor($student), $fee->id, $fee, true);
            $this->audit('advance', 'student_dues', $due, ['description' => "{$fee->fee_head} (" . money_inr($fee->amount) . ") charged in advance to {$student->full_name}"]);

            return $due;
        });
    }

    /** Can this advance fee still be cancelled? Only while no payment is confirmed or waiting for verification. */
    public function canRevokeAdvance(StudentDue $due): bool
    {
        return $due->advance && !$due->isSettled()
            && !$due->payments()->whereIn('status', ['pending_verification', 'success'])->exists();
    }

    /**
     * Undo "Pay in advance" (e.g. clicked by mistake): the advance fee is removed and becomes upcoming
     * again. Refused once a payment is confirmed or waiting — Accounts rejects that payment first.
     */
    public function revokeAdvance(StudentDue $due, ?string $reason = null): void
    {
        if (!$due->advance) {
            throw new RuntimeException('Only fees added as an advance can be cancelled here.');
        }
        if (!$this->canRevokeAdvance($due)) {
            throw new RuntimeException('This advance fee already has a payment. Accounts must reject the payment before it can be cancelled.');
        }

        $this->audit('revoke_advance', 'student_dues', $due, [
            'old' => $due->only(['fee_head', 'amount_due', 'due_date', 'period_no']),
            'reason' => $reason,
            'description' => "Advance fee {$due->fee_head} (" . money_inr($due->amount_due) . ") cancelled for {$due->student->full_name}",
        ]);
        $due->delete();
    }

    // ------------------------------------------------------------------ payments

    /**
     * Record a payment received outside an online gateway (GPay/UPI transfer, cash, bank, cheque).
     * It waits for Accounts verification — unless $confirm: recorded by Accounts (payments.verify), it is
     * confirmed at once (receipt, due updated, student notified) with no separate approval.
     */
    public function recordPayment(StudentDue $due, PaymentGateway $gateway, float $amount, $paidAt, ?string $reference, ?string $payerUpi, ?UploadedFile $proof, ?string $remarks, bool $confirm = false): StudentPayment
    {
        if ($confirm) {
            return DB::transaction(function () use ($due, $gateway, $amount, $paidAt, $reference, $payerUpi, $proof, $remarks) {
                $payment = $this->storePayment($due, $gateway, $amount, $paidAt, $reference, $payerUpi, $proof, $remarks);
                $this->approvePayment($payment, $remarks ?: 'Recorded and confirmed by Accounts');

                return $payment->fresh();
            });
        }

        $payment = $this->storePayment($due, $gateway, $amount, $paidAt, $reference, $payerUpi, $proof, $remarks);
        $this->inApp->notify('payment_submitted', $due->student, ['amount' => money_inr($amount), 'fee' => $due->fee_head, 'method' => $gateway->name]);

        return $payment;
    }

    /** Validate and store a payment as "waiting for verification" (see recordPayment). */
    protected function storePayment(StudentDue $due, PaymentGateway $gateway, float $amount, $paidAt, ?string $reference, ?string $payerUpi, ?UploadedFile $proof, ?string $remarks): StudentPayment
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

        $net = max(0, round((float) $due->amount_due - (float) $due->discount, 2)); // scholarships (Module 2C)
        $status = $due->status === 'waived'
            ? 'waived'
            : ($paid >= $net ? 'cleared' : ($paid > 0 ? 'partial' : 'pending'));

        $due->update(['amount_paid' => $paid, 'status' => $status]);
    }
}
