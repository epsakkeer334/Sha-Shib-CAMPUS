<?php

namespace App\Services;

use App\Models\Admin\Scholarship;
use App\Models\Admin\Student;
use App\Models\Admin\StudentDue;
use App\Models\Admin\StudentDueDiscount;
use App\Models\Admin\StudentScholarship;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Scholarship Management (Module 2C).
 *
 *  - Eligible students: same institute + course (+ batch), admitted (ER number), not withdrawn.
 *  - Eligible dues: fee-structure dues of the covered semesters (+ one-time lines with "all fees"),
 *    matching academic year if set, not waived.
 *  - Per due: percentage → amount × % ; fixed → the amount per semester split over that semester's
 *    lines in proportion to their amounts; capped at what is still open
 *    (amount − paid − payments waiting − other scholarships). Fully paid dues get nothing; no refunds.
 *  - Fees charged later (promotion, new fee line, advance) are discounted by applyTo().
 *  - Revoke removes the discount from dues without payments only.
 */
class ScholarshipService
{
    use RecordsAuditTrail;

    const ADMITTED = ['er_issued', 'active'];

    // ------------------------------------------------------------------ eligibility

    public function eligibleQuery(Scholarship $scholarship)
    {
        return Student::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('institute_id', $scholarship->institute_id)
            ->where('course_id', $scholarship->course_id)
            ->when($scholarship->batch_id, fn ($q) => $q->where('batch_id', $scholarship->batch_id))
            ->whereNotNull('er_number')
            ->whereIn('status', self::ADMITTED);
    }

    /** Why a student cannot get this scholarship (null = can). */
    public function problem(Scholarship $scholarship, Student $student): ?string
    {
        if (!$scholarship->status) {
            return 'The scholarship is inactive.';
        }
        if ((int) $student->institute_id !== (int) $scholarship->institute_id || (int) $student->course_id !== (int) $scholarship->course_id) {
            return 'Different institute or course.';
        }
        if ($scholarship->batch_id && (int) $student->batch_id !== (int) $scholarship->batch_id) {
            return 'Not in the scholarship\'s batch.';
        }
        if (!$student->er_number || !in_array($student->status, self::ADMITTED, true)) {
            return 'Not admitted yet (no ER number).';
        }
        if (StudentScholarship::withoutGlobalScopes()->where('student_id', $student->id)->where('scholarship_id', $scholarship->id)->where('status', 'active')->exists()) {
            return 'Already has this scholarship.';
        }

        return null;
    }

    protected function eligibleDues(Scholarship $scholarship, Student $student): Collection
    {
        return StudentDue::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('student_id', $student->id)->whereNotNull('course_fee_id')->where('status', '!=', 'waived')
            ->orderBy('period_no')->orderBy('id')->get()
            ->filter(fn ($d) => $scholarship->covers($d->period_no === null ? null : (int) $d->period_no)
                && (!$scholarship->academic_year_id || $d->period_no === null || (int) $d->academic_year_id === (int) $scholarship->academic_year_id))
            ->values();
    }

    // ------------------------------------------------------------------ calculation

    /**
     * Discount of one scholarship on each eligible due of a student (no saving).
     * Each row: due, base (before the cap), amount, room, note.
     */
    public function calculate(Scholarship $scholarship, Student $student, ?int $exceptAssignmentId = null): Collection
    {
        $dues = $this->eligibleDues($scholarship, $student);
        $rows = collect();

        foreach ($dues->groupBy(fn ($d) => $d->period_no === null ? 'once' : (int) $d->period_no) as $group) {
            $groupTotal = (float) $group->sum('amount_due');
            $split = 0.0;
            foreach ($group->values() as $i => $due) {
                if ($scholarship->type === 'percentage') {
                    $base = round((float) $due->amount_due * (float) $scholarship->value / 100, 2);
                } else {
                    $fixed = min((float) $scholarship->value, $groupTotal);
                    $base = $i === $group->count() - 1
                        ? round($fixed - $split, 2)                                        // last line takes the rounding
                        : round($groupTotal > 0 ? $fixed * (float) $due->amount_due / $groupTotal : 0, 2);
                    $split += $base;
                }
                $room = $this->room($due, $exceptAssignmentId);
                $amount = max(0, min($base, $room));
                $rows->push([
                    'due' => $due, 'base' => $base, 'amount' => $amount, 'room' => $room,
                    'note' => $room <= 0 ? 'already paid' : ($amount < $base ? 'capped at the open balance' : null),
                ]);
            }
        }

        return $rows;
    }

    /** What is still open on a due for this assignment: amount − paid − waiting − other discounts. */
    protected function room(StudentDue $due, ?int $exceptAssignmentId): float
    {
        $others = (float) StudentDueDiscount::where('student_due_id', $due->id)
            ->when($exceptAssignmentId, fn ($q) => $q->where('student_scholarship_id', '!=', $exceptAssignmentId))->sum('amount');
        $waiting = (float) $due->payments()->where('status', 'pending_verification')->sum('amount');

        return max(0, round((float) $due->amount_due - (float) $due->amount_paid - $waiting - $others, 2));
    }

    /**
     * Assign page preview for one student: per semester the fee, other discounts, this scholarship, paid
     * and the final payable; plus the semesters that are not charged yet.
     */
    public function preview(Scholarship $scholarship, Student $student): array
    {
        $calc = $this->calculate($scholarship, $student);
        $byPeriod = [];
        foreach ($calc as $row) {
            $due = $row['due'];
            $key = $due->period_no === null ? 0 : (int) $due->period_no;
            $byPeriod[$key] ??= ['period_no' => $key ?: null, 'fee' => 0, 'other' => 0, 'this' => 0, 'paid' => 0, 'payable' => 0, 'notes' => []];
            $other = (float) $due->discount;
            $byPeriod[$key]['fee'] += (float) $due->amount_due;
            $byPeriod[$key]['other'] += $other;
            $byPeriod[$key]['this'] += $row['amount'];
            $byPeriod[$key]['paid'] += (float) $due->amount_paid;
            $byPeriod[$key]['payable'] += max(0, (float) $due->amount_due - $other - $row['amount'] - (float) $due->amount_paid);
            if ($row['note']) {
                $byPeriod[$key]['notes'][$row['note']] = true;
            }
        }
        ksort($byPeriod);
        $charged = array_keys($byPeriod);
        $later = array_values(array_diff($scholarship->periods, $charged));

        return [
            'periods' => array_values(array_map(fn ($p) => array_merge($p, ['notes' => array_keys($p['notes'])]), $byPeriod)),
            'later' => $later,
            'total' => round(array_sum(array_column($byPeriod, 'this')), 2),
        ];
    }

    /**
     * A student's complete fee structure for the Assign page: every charged fee (and the upcoming ones not
     * charged yet), grouped by semester, with the existing discount, this scholarship, paid and final payable.
     * Upcoming fees show the scholarship they will get when charged ("projected").
     *
     * @return array{groups: array, totals: array}
     */
    public function structure(Scholarship $scholarship, Student $student): array
    {
        $course = $student->course;
        $calc = $this->calculate($scholarship, $student)->keyBy(fn ($r) => $r['due']->id);
        $label = fn ($periodNo) => $periodNo ? ($course ? $course->periodName((int) $periodNo) : "Period {$periodNo}") : 'One-time fees';
        $groups = [];

        $dues = StudentDue::withoutGlobalScopes()->whereNull('deleted_at')->where('student_id', $student->id)
            ->orderByRaw('period_no IS NOT NULL')->orderBy('period_no')->orderBy('due_date')->orderBy('id')->get();
        foreach ($dues as $due) {
            $key = $due->period_no === null ? 0 : (int) $due->period_no;
            $row = $calc->get($due->id);
            $waived = $due->status === 'waived';
            $this_ = $waived || !$row ? 0.0 : (float) $row['amount'];
            $groups[$key]['label'] ??= $label($due->period_no);
            $groups[$key]['lines'][] = [
                'fee' => $due->fee_head, 'amount' => (float) $due->amount_due, 'existing' => (float) $due->discount, 'this' => $this_,
                'paid' => (float) $due->amount_paid,
                'payable' => $waived ? 0.0 : max(0, round((float) $due->amount_due - (float) $due->discount - $this_ - (float) $due->amount_paid, 2)),
                'status' => $waived ? 'Waived' : ($due->status === 'cleared' ? 'Paid' : ($due->status === 'partial' ? 'Part paid' : 'Due ' . $due->due_date->format('d M Y'))),
                'covered' => (bool) $row, 'note' => $row['note'] ?? null, 'upcoming' => false, 'waived' => $waived,
            ];
        }

        // Not charged yet: projected discount (same rules as when they are charged)
        $upcoming = app(FeeService::class)->upcomingFees($student)->groupBy(fn ($i) => (int) ($i['period_no'] ?? 0));
        foreach ($upcoming as $key => $items) {
            $covered = $items->filter(fn ($i) => $scholarship->covers($i['period_no'] ? (int) $i['period_no'] : null)
                && (!$scholarship->academic_year_id || !$i['period_no'] || (int) optional(app(PeriodService::class)->periodFor($student, (int) $i['period_no']))->academic_year_id === (int) $scholarship->academic_year_id));
            $coveredTotal = (float) $covered->sum(fn ($i) => (float) $i['fee']->amount);
            // fixed amount is per semester: what the charged fees of this semester already use is not given twice
            $usedNow = (float) collect($groups[$key]['lines'] ?? [])->where('upcoming', false)->sum('this');
            $fixed = max(0, min((float) $scholarship->value, $coveredTotal + $usedNow) - $usedNow);
            foreach ($items as $item) {
                $amount = (float) $item['fee']->amount;
                $isCovered = $covered->contains(fn ($c) => $c['fee']->id === $item['fee']->id);
                $this_ = !$isCovered ? 0.0 : ($scholarship->type === 'percentage'
                    ? round($amount * (float) $scholarship->value / 100, 2)
                    : round($coveredTotal > 0 ? $fixed * $amount / $coveredTotal : 0, 2));
                $groups[$key]['label'] ??= $label($item['period_no']);
                $groups[$key]['lines'][] = [
                    'fee' => $item['fee']->fee_head, 'amount' => $amount, 'existing' => 0.0, 'this' => $this_, 'paid' => 0.0,
                    'payable' => max(0, round($amount - $this_, 2)), 'status' => $item['when'],
                    'covered' => $isCovered, 'note' => null, 'upcoming' => true, 'waived' => false,
                ];
            }
        }
        ksort($groups);

        $totals = ['fees' => 0.0, 'existing' => 0.0, 'this_now' => 0.0, 'this_later' => 0.0, 'paid' => 0.0, 'payable_now' => 0.0, 'payable_later' => 0.0];
        foreach ($groups as $key => $group) {
            $sub = ['amount' => 0.0, 'existing' => 0.0, 'this' => 0.0, 'paid' => 0.0, 'payable' => 0.0];
            foreach ($group['lines'] as $line) {
                if ($line['waived']) {
                    continue;
                }
                foreach ($sub as $k => $v) {
                    $sub[$k] += $line[$k];
                }
                $totals['fees'] += $line['amount'];
                $totals['existing'] += $line['existing'];
                $totals['paid'] += $line['paid'];
                $totals[$line['upcoming'] ? 'this_later' : 'this_now'] += $line['this'];
                $totals[$line['upcoming'] ? 'payable_later' : 'payable_now'] += $line['payable'];
            }
            $groups[$key]['subtotal'] = array_map(fn ($v) => round($v, 2), $sub);
            $groups[$key]['covered'] = collect($group['lines'])->contains('covered', true);
            $groups[$key]['upcoming'] = collect($group['lines'])->every(fn ($l) => $l['upcoming']);
        }

        return ['groups' => array_values($groups), 'totals' => array_map(fn ($v) => round($v, 2), $totals)];
    }

    // ------------------------------------------------------------------ assign / apply / revoke

    /**
     * Give the scholarship to students (ids). Ineligible / duplicate ones are skipped with the reason.
     *
     * @return array{assigned: int, skipped: array<int, string>, discount: float}
     */
    public function assign(Scholarship $scholarship, array $studentIds, ?string $remarks = null): array
    {
        $out = ['assigned' => 0, 'skipped' => [], 'discount' => 0.0];
        $students = Student::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('id', $studentIds)->get()->keyBy('id');

        foreach (array_unique(array_map('intval', $studentIds)) as $id) {
            $student = $students->get($id);
            if (!$student) {
                $out['skipped'][$id] = 'Student not found.';
                continue;
            }
            if ($problem = $this->problem($scholarship, $student)) {
                $out['skipped'][$id] = $problem;
                continue;
            }

            $discount = DB::transaction(function () use ($scholarship, $student, $remarks) {
                $assignment = StudentScholarship::withoutGlobalScopes()->firstOrNew(['student_id' => $student->id, 'scholarship_id' => $scholarship->id]);
                $assignment->fill([
                    'institute_id' => $student->institute_id, 'status' => 'active', 'assigned_by' => Auth::id(), 'assigned_at' => now(),
                    'revoked_by' => null, 'revoked_at' => null, 'remarks' => $remarks,
                ])->save();
                $discount = $this->applyAssignment($assignment);

                $this->audit('scholarship_assigned', 'scholarships', $student, [
                    'new' => ['scholarship' => $scholarship->code, 'discount' => $discount],
                    'description' => "Scholarship {$scholarship->name} ({$scholarship->value_label}) given to {$student->full_name} — discount " . money_inr($discount),
                ]);

                return $discount;
            });

            app(AppNotifier::class)->notify('scholarship_awarded', $student->fresh(), ['scholarship' => $scholarship->name, 'amount' => money_inr($discount)]);
            $out['assigned']++;
            $out['discount'] += $discount;
        }

        return $out;
    }

    /** (Re)calculate the discounts of one assignment on the student's current dues. Returns its total. */
    public function applyAssignment(StudentScholarship $assignment): float
    {
        $scholarship = $assignment->scholarship()->with('periodRows')->first();
        $student = Student::withoutGlobalScopes()->find($assignment->student_id);
        if (!$scholarship || !$student || $assignment->status !== 'active') {
            return 0.0;
        }

        $touched = [];
        foreach ($this->calculate($scholarship, $student, $assignment->id) as $row) {
            $due = $row['due'];
            $existing = StudentDueDiscount::where('student_due_id', $due->id)->where('student_scholarship_id', $assignment->id)->first();
            // a settled due keeps the discount it was settled with
            if ($existing && $due->status === 'cleared') {
                continue;
            }
            if ($row['amount'] > 0) {
                StudentDueDiscount::updateOrCreate(['student_due_id' => $due->id, 'student_scholarship_id' => $assignment->id], ['amount' => $row['amount']]);
            } elseif ($existing) {
                $existing->delete();
            }
            $touched[$due->id] = $due;
        }
        foreach ($touched as $due) {
            $this->refreshDue($due);
        }

        return round((float) StudentDueDiscount::where('student_scholarship_id', $assignment->id)->sum('amount'), 2);
    }

    /** A due was just charged (FeeService::createDue): discount it by the student's active scholarships. */
    public function applyTo(StudentDue $due): void
    {
        if (!$due->course_fee_id) {
            return;
        }
        $assignments = StudentScholarship::withoutGlobalScopes()->active()->where('student_id', $due->student_id)->with('scholarship.periodRows')->get();
        foreach ($assignments as $assignment) {
            if ($assignment->scholarship && $assignment->scholarship->covers($due->period_no === null ? null : (int) $due->period_no)) {
                $this->applyAssignment($assignment);
            }
        }
    }

    /** discount = sum of its scholarship rows; status follows (cleared when nothing is left to pay). */
    public function refreshDue(StudentDue $due): void
    {
        $due->update(['discount' => round((float) StudentDueDiscount::where('student_due_id', $due->id)->sum('amount'), 2)]);
        app(FeeService::class)->recalculate($due->fresh());
    }

    /** Take a scholarship back: discounts stay only on dues that already have payments. */
    public function revoke(StudentScholarship $assignment, string $reason): void
    {
        if ($assignment->status !== 'active') {
            throw new RuntimeException('This scholarship is already revoked.');
        }

        DB::transaction(function () use ($assignment, $reason) {
            $assignment->update(['status' => 'revoked', 'revoked_by' => Auth::id(), 'revoked_at' => now(), 'remarks' => trim(($assignment->remarks ? $assignment->remarks . ' | ' : '') . 'Revoked: ' . $reason)]);
            $removed = 0.0;
            foreach ($assignment->discounts()->with('due')->get() as $row) {
                $due = $row->due;
                if ($due && !$this->hasPayments($due)) {
                    $removed += (float) $row->amount;
                    $row->delete();
                    $this->refreshDue($due);
                }
            }
            $student = Student::withoutGlobalScopes()->find($assignment->student_id);
            $this->audit('scholarship_revoked', 'scholarships', $student, [
                'reason' => $reason, 'old' => ['scholarship' => optional($assignment->scholarship)->code],
                'description' => 'Scholarship ' . optional($assignment->scholarship)->name . " revoked for {$student->full_name} — " . money_inr($removed) . ' discount removed',
            ]);
            app(AppNotifier::class)->notify('scholarship_revoked', $student, ['scholarship' => optional($assignment->scholarship)->name, 'reason' => $reason]);
        });
    }

    /** After the scholarship itself was edited: recalculate every active assignment on dues without payments. */
    public function reapply(Scholarship $scholarship): int
    {
        $count = 0;
        foreach ($scholarship->assignments()->withoutGlobalScopes()->where('status', 'active')->get() as $assignment) {
            DB::transaction(function () use ($assignment) {
                foreach ($assignment->discounts()->with('due')->get() as $row) {
                    if ($row->due && !$this->hasPayments($row->due)) {
                        $row->delete();
                        $this->refreshDue($row->due);
                    }
                }
                $this->applyAssignment($assignment->fresh());
            });
            $count++;
        }

        return $count;
    }

    protected function hasPayments(StudentDue $due): bool
    {
        return (float) $due->amount_paid > 0 || $due->payments()->whereIn('status', ['pending_verification', 'success'])->exists();
    }
}
