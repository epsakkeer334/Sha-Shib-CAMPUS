<?php

namespace App\Services;

use App\Models\Admin\AddonProgram;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\Student;
use App\Models\Admin\StudentAddonProgram;
use App\Traits\RecordsAuditTrail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Module 2 step 2.10 — add-on programs (optional extras per institute course).
 *  - options(): what an applicant can choose (active add-ons of the institute course + the ones they already have)
 *  - validate(): course maximum, seats, belongs to the course
 *  - sync(): saves the choices; a paid add-on adds a due (FeeService), removing it deletes the due if unpaid.
 *    Choices of another course (course changed) are removed the same way.
 */
class AddonProgramService
{
    use RecordsAuditTrail;

    public function __construct(protected FeeService $fees)
    {
    }

    /** Most add-ons a student may choose for the institute course (null = no limit). */
    public function maxFor($instituteId, $courseId): ?int
    {
        if (!$instituteId || !$courseId) {
            return null;
        }
        $max = InstituteCourse::where('institute_id', $instituteId)->where('course_id', $courseId)->value('max_addon_programs');

        return $max === null ? null : (int) $max;
    }

    /**
     * Add-ons to show: active add-ons of the institute course, plus inactive ones the student already chose.
     * Each gets ->seats_left (null = no limit) and ->is_full.
     */
    public function options($instituteId, $courseId, ?Student $student = null): Collection
    {
        if (!$instituteId || !$courseId) {
            return collect();
        }
        $chosen = $student ? $student->addonChoices()->pluck('addon_program_id')->all() : [];

        return AddonProgram::withoutGlobalScopes()->whereNull('deleted_at')->ofCourse($instituteId, $courseId)
            ->where(fn ($q) => $q->where('status', true)->orWhereIn('id', $chosen ?: [0]))
            ->orderBy('sort_order')->orderBy('name')->get()
            ->each(function (AddonProgram $program) use ($chosen) {
                $program->seats_left = $program->seatsLeft();
                $program->is_full = $program->seats_left === 0 && !in_array($program->id, $chosen, true);
                $program->is_chosen = in_array($program->id, $chosen, true);
            });
    }

    /**
     * Problems with a selection for the institute course (empty array = fine). Already chosen add-ons may stay
     * even when they are now inactive or full.
     */
    public function validate(array $ids, $instituteId, $courseId, ?Student $student = null): array
    {
        $ids = $this->clean($ids);
        if (!$ids) {
            return [];
        }
        $options = $this->options($instituteId, $courseId, $student)->keyBy('id');
        $errors = [];

        foreach ($ids as $id) {
            $program = $options->get($id);
            if (!$program) {
                $errors[] = 'One of the chosen add-on programs is not offered for this course.';
                continue;
            }
            if (!$program->is_chosen && !$program->status) {
                $errors[] = "{$program->name} is no longer offered.";
            } elseif ($program->is_full) {
                $errors[] = "{$program->name} is full.";
            }
        }

        $max = $this->maxFor($instituteId, $courseId);
        if ($max !== null && count($ids) > $max) {
            $errors[] = 'You can choose at most ' . $max . ' add-on ' . ($max === 1 ? 'program' : 'programs') . ' for this course.';
        }

        return array_values(array_unique($errors));
    }

    /**
     * Save the student's choices. Returns [added, removed] names. Call inside the caller's transaction or alone.
     */
    public function sync(Student $student, array $ids, string $by = 'student'): array
    {
        $ids = $this->clean($ids);

        return DB::transaction(function () use ($student, $ids, $by) {
            $current = $student->addonChoices()->with(['program', 'due.payments'])->get();
            $valid = AddonProgram::withoutGlobalScopes()->ofCourse($student->institute_id, $student->course_id)->whereIn('id', $ids ?: [0])->get()->keyBy('id');
            $added = $removed = [];

            // removed, or of another course (course changed)
            foreach ($current as $choice) {
                if ($valid->has($choice->addon_program_id)) {
                    continue;
                }
                if ($choice->due && $choice->due->payments->whereIn('status', ['pending_verification', 'success'])->isNotEmpty()) {
                    continue; // paid add-on stays (the office can waive / handle it)
                }
                if ($choice->due && $choice->due->status !== 'waived') {
                    $this->fees->deleteDue($choice->due);
                }
                $removed[] = optional($choice->program)->name;
                $choice->delete();
            }

            $have = $student->addonChoices()->pluck('addon_program_id')->all();
            foreach ($valid as $program) {
                if (in_array($program->id, $have, true)) {
                    continue;
                }
                $due = null;
                if (!$program->isFree()) {
                    $start = $student->joining_date ? Carbon::parse($student->joining_date) : today();
                    $due = $this->fees->addDue($student, 'Add-on: ' . $program->name, (float) $program->fee_amount, $start->copy()->addDays((int) $program->fee_due_days));
                }
                StudentAddonProgram::create([
                    'student_id' => $student->id, 'addon_program_id' => $program->id, 'student_due_id' => optional($due)->id,
                    'selected_at' => now(), 'selected_by' => Auth::id(),
                ]);
                $added[] = $program->name;
            }

            if ($added || $removed) {
                $who = $by === 'admin' ? 'Office' : 'Student';
                $this->audit('update', 'student_addon_programs', $student, [
                    'old' => ['removed' => implode(', ', array_filter($removed))], 'new' => ['added' => implode(', ', $added)],
                    'description' => "{$who} changed add-on programs of {$student->full_name}"
                        . ($added ? ' · added ' . implode(', ', $added) : '') . ($removed ? ' · removed ' . implode(', ', array_filter($removed)) : ''),
                ]);
            }

            return [$added, array_filter($removed)];
        });
    }

    /** Ids of the add-ons a student has chosen. */
    public function chosenIds(Student $student): array
    {
        return $student->addonChoices()->pluck('addon_program_id')->map(fn ($id) => (int) $id)->all();
    }

    protected function clean(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}
