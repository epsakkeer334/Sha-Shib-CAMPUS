<?php

namespace App\Services;

use App\Models\Admin\AcademicYear;
use App\Models\Admin\Batch;
use App\Models\Admin\Course;
use App\Models\Admin\CoursePeriod;
use App\Models\Admin\Institute;
use App\Models\Admin\Student;
use App\Models\Admin\StudentPeriod;
use App\Traits\RecordsAuditTrail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Module 2B — academic periods (semesters / terms / modules / years).
 *  - generate(): the periods of an institute course for one intake (academic year of period 1),
 *    optionally for one batch, from the course's period structure (label, length, number of periods)
 *  - a student's calendar = their batch's periods, else the institute course periods of the intake
 *    year of their joining date
 *  - period 1 starts when the ER number is issued; moving on is Module 5 (promotion)
 */
class PeriodService
{
    use RecordsAuditTrail;

    /**
     * Generate every period of the course from $startDate (period n starts (n-1) × length months later).
     *
     * @return Collection<CoursePeriod>
     */
    public function generate(Institute $institute, Course $course, $startDate, ?Batch $batch = null): Collection
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $intake = AcademicYear::forDate($start);
        if (!$intake) {
            throw new RuntimeException('No academic year covers ' . $start->format('d M Y') . '. Add it under Master Data → Academic Years first.');
        }
        if ($batch && ((int) $batch->institute_id !== (int) $institute->id || (int) $batch->course_id !== (int) $course->id)) {
            throw new RuntimeException('The batch does not belong to this institute course.');
        }
        if ($this->calendarQuery($institute->id, $course->id, $intake->id, optional($batch)->id)->exists()) {
            throw new RuntimeException('Periods already exist for this course' . ($batch ? " and batch {$batch->code}" : " and intake {$intake->name}") . '. Edit or remove them first.');
        }

        $length = $course->period_length;

        return DB::transaction(function () use ($institute, $course, $start, $intake, $batch, $length) {
            $periods = collect();
            for ($n = 1; $n <= $course->total_periods; $n++) {
                $from = $start->copy()->addMonthsNoOverflow(($n - 1) * $length);
                $to = $start->copy()->addMonthsNoOverflow($n * $length)->subDay();
                $periods->push(CoursePeriod::create([
                    'institute_id' => $institute->id, 'course_id' => $course->id, 'batch_id' => optional($batch)->id,
                    'intake_academic_year_id' => $intake->id, 'academic_year_id' => optional(AcademicYear::forDate($from))->id,
                    'period_no' => $n, 'label' => $course->periodName($n), 'year_of_study' => $course->yearOfStudy($n),
                    'start_date' => $from->toDateString(), 'end_date' => $to->toDateString(), 'status' => 'planned',
                ]));
            }

            $this->audit('generate', 'course_periods', $periods->first(), [
                'description' => "Generated {$periods->count()} {$course->period_label} periods for {$course->code} at {$institute->name}"
                    . ($batch ? " (batch {$batch->code})" : " (intake {$intake->name})") . ' from ' . $start->format('d M Y'),
            ]);

            return $periods;
        });
    }

    /**
     * Change one period's dates / status. A later start of the next period is not moved automatically.
     */
    public function update(CoursePeriod $period, $startDate, $endDate, string $status): void
    {
        if (!array_key_exists($status, CoursePeriod::STATUSES)) {
            throw new RuntimeException('Unknown period status.');
        }
        $old = $period->only(['start_date', 'end_date', 'status']);
        $from = Carbon::parse($startDate);
        $period->update([
            'start_date' => $from->toDateString(), 'end_date' => Carbon::parse($endDate)->toDateString(), 'status' => $status,
            'academic_year_id' => optional(AcademicYear::forDate($from))->id,
        ]);
        $this->auditUpdate($period, 'course_periods', $old, $period->only(['start_date', 'end_date', 'status']), "Updated {$period->label} of " . optional($period->course)->code);
    }

    /**
     * Remove a calendar (all periods of one intake / batch) — only while no student is in it.
     */
    public function deleteCalendar(CoursePeriod $anyPeriod): int
    {
        $periods = $this->calendarQuery($anyPeriod->institute_id, $anyPeriod->course_id, $anyPeriod->intake_academic_year_id, $anyPeriod->batch_id)->get();
        if (StudentPeriod::withoutGlobalScopes()->whereIn('course_period_id', $periods->pluck('id'))->exists()) {
            throw new RuntimeException('Students are already in these periods, so they cannot be removed.');
        }
        $this->audit('delete', 'course_periods', $anyPeriod, ['description' => "Removed {$periods->count()} periods of " . optional($anyPeriod->course)->code]);
        $periods->each->delete();

        return $periods->count();
    }

    protected function calendarQuery($instituteId, $courseId, $intakeId, $batchId)
    {
        return CoursePeriod::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('institute_id', $instituteId)->where('course_id', $courseId)->where('intake_academic_year_id', $intakeId)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId), fn ($q) => $q->whereNull('batch_id'));
    }

    /**
     * The student's calendar: their batch's periods, else the institute course periods of the intake year
     * of the joining date (without batch).
     */
    public function calendarFor(Student $student): Collection
    {
        if ($student->batch_id) {
            $byBatch = CoursePeriod::withoutGlobalScopes()->whereNull('deleted_at')->where('batch_id', $student->batch_id)
                ->where('course_id', $student->course_id)->orderBy('period_no')->get();
            if ($byBatch->isNotEmpty()) {
                return $byBatch;
            }
        }
        $intake = AcademicYear::forDate($student->joining_date);
        if (!$intake) {
            return collect();
        }

        return $this->calendarQuery($student->institute_id, $student->course_id, $intake->id, null)->orderBy('period_no')->get();
    }

    public function periodFor(Student $student, int $periodNo): ?CoursePeriod
    {
        return $this->calendarFor($student)->firstWhere('period_no', $periodNo);
    }

    /** Current period number: the student's current period row, else 1 (admission). */
    public function currentPeriodNo(Student $student): int
    {
        return (int) (optional($student->currentPeriod()->first())->period_no ?: 1);
    }

    /** Academic year of the student's current period (or of the joining date). */
    public function currentAcademicYearId(Student $student): ?int
    {
        $row = $student->currentPeriod()->with('coursePeriod')->first();

        return optional(optional($row)->coursePeriod)->academic_year_id
            ?? optional(optional($this->periodFor($student, 1)))->academic_year_id
            ?? optional(AcademicYear::forDate($student->joining_date))->id;
    }

    /** Intake academic year of the student (academic year of period 1 / joining date). */
    public function intakeAcademicYearId(Student $student): ?int
    {
        return optional($this->periodFor($student, 1))->intake_academic_year_id ?? optional(AcademicYear::forDate($student->joining_date))->id;
    }

    /**
     * Admission confirmed (ER number): the student starts period 1 of their calendar.
     * Nothing happens when the course has no periods set up yet.
     */
    public function startFirstPeriod(Student $student): ?StudentPeriod
    {
        if ($student->periods()->exists()) {
            return $student->currentPeriod()->first();
        }
        $first = $this->periodFor($student, 1);
        if (!$first) {
            return null;
        }

        $row = StudentPeriod::create([
            'institute_id' => $student->institute_id, 'student_id' => $student->id, 'course_period_id' => $first->id,
            'period_no' => 1, 'status' => 'current', 'started_on' => max(now()->startOfDay(), $first->start_date)->toDateString(),
        ]);
        $this->audit('start_period', 'student_periods', $row, ['description' => "{$student->full_name} started {$first->label}"]);

        return $row;
    }
}
