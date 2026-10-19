<?php

namespace App\Services;

use App\Models\Admin\CourseEligibilityCriterion;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\QualifyingSubjectGroup;
use App\Models\Admin\Student;
use App\Models\Admin\StudentQualifyingMark;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Course eligibility — Higher Secondary / 12th (Module 2 step 2.7).
 *
 * The Super Admin sets, per course, which 12th subject groups are accepted (for one board or any board)
 * with a minimum % per required subject and an optional minimum aggregate. CGPA is converted with the
 * board's factor. A student who is not eligible cannot submit the application unless an admin with
 * `eligibility.override` allows it with a reason. Shared by the admin onboarding page and the portal.
 */
class EligibilityService
{
    use RecordsAuditTrail;

    // ------------------------------------------------------------------ criteria

    /** Active criteria of a course with board, group and subject minimums (for display and checks). */
    public function criteriaFor(?int $courseId): Collection
    {
        if (!$courseId) {
            return collect();
        }

        return CourseEligibilityCriterion::active()->where('course_id', $courseId)
            ->with(['board', 'group', 'subjects'])
            ->whereHas('group', fn ($g) => $g->where('status', true))
            ->get()
            ->sortBy(fn ($c) => [optional($c->group)->code, $c->higher_secondary_board_id ? 0 : 1])
            ->values();
    }

    /**
     * Subject groups to choose from: the groups the course accepts (all active groups when the course has
     * no criteria), plus the student's saved group so it stays selectable.
     */
    public function groupsFor(?int $courseId, ?string $currentCode = null): Collection
    {
        $criteria = $this->criteriaFor($courseId);
        $query = QualifyingSubjectGroup::active()->with('subjects')->orderBy('code');
        if ($criteria->isNotEmpty()) {
            $query->whereIn('id', $criteria->pluck('qualifying_subject_group_id')->unique());
        }
        $groups = $query->get();

        if ($currentCode && !$groups->contains('code', $currentCode)
            && ($current = QualifyingSubjectGroup::with('subjects')->where('code', $currentCode)->first())) {
            $groups->push($current);
        }

        return $groups;
    }

    /** The criterion for a course + board + group: the board's own one wins over "any board". */
    public function criterionFor(?int $courseId, ?int $boardId, ?int $groupId): ?CourseEligibilityCriterion
    {
        if (!$courseId || !$groupId) {
            return null;
        }
        $matching = $this->criteriaFor($courseId)->where('qualifying_subject_group_id', $groupId);

        return $matching->first(fn ($c) => $boardId && (int) $c->higher_secondary_board_id === (int) $boardId)
            ?? $matching->first(fn ($c) => $c->higher_secondary_board_id === null);
    }

    /**
     * Subjects to enter marks for: the criterion's subjects (required, with minimums), else the group's
     * subjects (optional). Each: id, name, min (null = no minimum).
     *
     * @return array{criterion: ?CourseEligibilityCriterion, required: bool, subjects: array}
     */
    public function subjectsToEnter(?int $courseId, ?int $boardId, ?string $groupCode): array
    {
        $group = $groupCode ? QualifyingSubjectGroup::with('subjects')->where('code', $groupCode)->first() : null;
        $criterion = $group ? $this->criterionFor($courseId, $boardId, $group->id) : null;

        if ($criterion) {
            $order = $group->subjects->pluck('id')->flip();
            $subjects = $criterion->subjects
                ->sortBy(fn ($s) => $order[$s->qualifying_subject_id] ?? 99)
                ->map(fn ($s) => ['id' => $s->qualifying_subject_id, 'name' => optional($s->subject)->name, 'min' => (float) $s->min_percent])
                ->values()->all();
        } else {
            $subjects = $group ? $group->subjects->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'min' => null])->values()->all() : [];
        }

        return ['criterion' => $criterion, 'required' => (bool) $criterion, 'subjects' => $subjects];
    }

    public function toPercent($mark, ?string $type, $board = null): ?float
    {
        if ($mark === null || $mark === '' || !is_numeric($mark)) {
            return null;
        }
        if ($type !== 'cgpa') {
            return round((float) $mark, 2);
        }
        $board = $board instanceof HigherSecondaryBoard ? $board : ($board ? HigherSecondaryBoard::find($board) : null);
        $factor = (float) (optional($board)->cgpa_factor ?: 9.5);

        return round(min(100, (float) $mark * $factor), 2);
    }

    // ------------------------------------------------------------------ check

    /**
     * Check form values against the course criteria (no saving).
     * $in: course_id, board_id, group_code, mark_type, marks [subject id => mark].
     *
     * @return array{status: string, criterion_id: ?int, group: ?string, board: ?string, subjects: array,
     *               aggregate: ?float, min_aggregate: ?float, aggregate_ok: ?bool, reasons: array, accepted: array, fingerprint: string}
     */
    public function check(array $in): array
    {
        $courseId = $in['course_id'] ?? null;
        $boardId = $in['board_id'] ?? null;
        $groupCode = $in['group_code'] ?? null;
        $type = $in['mark_type'] ?? 'percentage';
        $marks = $in['marks'] ?? [];

        $criteria = $this->criteriaFor($courseId);
        $board = $boardId ? HigherSecondaryBoard::find($boardId) : null;
        $group = $groupCode ? QualifyingSubjectGroup::where('code', $groupCode)->first() : null;
        $accepted = $criteria->map(fn ($c) => optional($c->group)->code)->filter()->unique()->values()->all();

        $result = [
            'status' => 'not_configured', 'criterion_id' => null, 'group' => $groupCode, 'board' => optional($board)->name,
            'subjects' => [], 'aggregate' => null, 'min_aggregate' => null, 'aggregate_ok' => null, 'reasons' => [],
            'accepted' => $accepted, 'fingerprint' => $this->fingerprint($courseId, $boardId, $groupCode, $type, $marks),
        ];

        if ($criteria->isEmpty()) {
            return $result; // course without criteria: nothing to check
        }

        $criterion = $group ? $this->criterionFor($courseId, $boardId, $group->id) : null;
        if (!$criterion) {
            $result['status'] = 'not_eligible';
            $result['reasons'][] = $group
                ? "This course accepts " . implode(' / ', $accepted) . " in 12th" . ($board && in_array($groupCode, $accepted, true) ? " (not from {$board->name})" : '') . "; {$group->code} does not meet it."
                : 'Choose your 12th subject group.';

            return $result;
        }

        $result['criterion_id'] = $criterion->id;
        $ok = true;
        $sum = 0;
        $count = 0;
        foreach ($this->subjectsToEnter($courseId, $boardId, $groupCode)['subjects'] as $subject) {
            $mark = $marks[$subject['id']] ?? null;
            $percent = $this->toPercent($mark, $type, $board);
            $passed = $percent !== null && $percent >= $subject['min'];
            $result['subjects'][] = $subject + ['mark' => $mark === null || $mark === '' ? null : (float) $mark, 'type' => $type, 'percent' => $percent, 'ok' => $passed];

            if ($percent === null) {
                $result['reasons'][] = "{$subject['name']}: mark not entered.";
                $ok = false;
            } else {
                $sum += $percent;
                $count++;
                if (!$passed) {
                    $result['reasons'][] = "{$subject['name']} " . $this->pct($percent) . " — needs " . $this->pct($subject['min']) . '.';
                    $ok = false;
                }
            }
        }

        if ($count) {
            $result['aggregate'] = round($sum / $count, 2);
        }
        if ($criterion->min_aggregate !== null) {
            $result['min_aggregate'] = (float) $criterion->min_aggregate;
            $result['aggregate_ok'] = $result['aggregate'] !== null && $count === count($result['subjects']) && $result['aggregate'] >= $result['min_aggregate'];
            if ($result['aggregate'] !== null && !$result['aggregate_ok'] && $count === count($result['subjects'])) {
                $result['reasons'][] = "Aggregate " . $this->pct($result['aggregate']) . " — needs " . $this->pct($result['min_aggregate']) . '.';
            }
            $ok = $ok && $result['aggregate_ok'];
        }

        $result['status'] = $ok ? 'eligible' : 'not_eligible';

        return $result;
    }

    protected function pct($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . ' %';
    }

    protected function fingerprint($courseId, $boardId, $groupCode, $type, array $marks): string
    {
        ksort($marks);
        $marks = array_map(fn ($m) => $m === null || $m === '' ? null : round((float) $m, 2), $marks);

        return sha1(json_encode([(int) $courseId, (int) $boardId, (string) $groupCode, (string) $type, $marks]));
    }

    // ------------------------------------------------------------------ student

    /** Form-style values of a saved student (course, board, group, marks). */
    public function valuesOf(Student $student): array
    {
        $academic = $student->academicDetail;

        return [
            'course_id' => $student->course_id,
            'board_id' => optional($academic)->higher_secondary_board_id,
            'group_code' => optional($academic)->higher_secondary_subject,
            'mark_type' => optional($academic)->higher_secondary_mark_type ?: 'percentage',
            'marks' => $student->qualifyingMarks()->get()->mapWithKeys(fn ($m) => [$m->qualifying_subject_id => (float) $m->mark])->all(),
        ];
    }

    /**
     * Save the 12th subject marks (the mark type of the 12th section) and re-check eligibility.
     * $marks: [subject id => mark]; subjects not listed are removed.
     */
    public function saveMarks(Student $student, array $marks, ?string $type): void
    {
        $board = optional($student->academicDetail)->higherSecondaryBoard;
        $marks = array_filter($marks, fn ($m) => $m !== null && $m !== '' && is_numeric($m));

        DB::transaction(function () use ($student, $marks, $type, $board) {
            $student->qualifyingMarks()->whereNotIn('qualifying_subject_id', array_keys($marks) ?: [0])->delete();
            foreach ($marks as $subjectId => $mark) {
                StudentQualifyingMark::updateOrCreate(
                    ['student_id' => $student->id, 'qualifying_subject_id' => (int) $subjectId],
                    ['mark_type' => $type ?: 'percentage', 'mark' => $mark, 'percent' => $this->toPercent($mark, $type, $board)]
                );
            }
        });

        $this->evaluate($student->fresh());
    }

    /**
     * Check the saved details and store the result on the academic details. An admin's override stays
     * only while course, board, group and marks are unchanged.
     */
    public function evaluate(Student $student): ?array
    {
        $academic = $student->academicDetail;
        if (!$academic) {
            return null;
        }

        $result = $this->check($this->valuesOf($student));
        $keepOverride = $academic->eligibility_status === 'overridden'
            && ($academic->eligibility_snapshot['fingerprint'] ?? null) === $result['fingerprint']
            && $result['status'] === 'not_eligible';

        $academic->forceFill([
            'eligibility_status' => $keepOverride ? 'overridden' : $result['status'],
            'eligibility_snapshot' => $result,
            'eligibility_checked_at' => now(),
        ] + ($keepOverride ? [] : ['eligibility_override_by' => null, 'eligibility_override_at' => null, 'eligibility_override_reason' => null]))->save();

        return $result;
    }

    /** Reasons that stop submission ([] when the student may submit). */
    public function blockingReasons(Student $student): array
    {
        $academic = $student->academicDetail;

        return $academic && $academic->eligibility_status === 'not_eligible'
            ? ($academic->eligibility_snapshot['reasons'] ?? ['The student does not meet the course eligibility criteria.'])
            : [];
    }

    /** Allow a not-eligible student to submit (Super Admin / Institute Admin with eligibility.override). */
    public function override(Student $student, string $reason): void
    {
        $academic = $student->academicDetail;
        $this->evaluate($student);
        $academic->refresh();

        if ($academic->eligibility_status !== 'not_eligible') {
            throw new RuntimeException('Only a student who does not meet the criteria can be allowed.');
        }

        $academic->forceFill([
            'eligibility_status' => 'overridden',
            'eligibility_override_by' => Auth::id(),
            'eligibility_override_at' => now(),
            'eligibility_override_reason' => $reason,
        ])->save();

        $this->audit('override', 'students', $student, [
            'old' => ['eligibility_status' => 'not_eligible'], 'new' => ['eligibility_status' => 'overridden'],
            'reason' => $reason,
            'description' => "Allowed {$student->full_name} despite the course eligibility criteria: " . implode(' ', $academic->eligibility_snapshot['reasons'] ?? []),
        ]);
    }

    /** "Physics ≥ 50 %, Chemistry ≥ 45 % · aggregate ≥ 50 %" */
    public static function describe(CourseEligibilityCriterion $criterion): string
    {
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') . ' %';
        $text = $criterion->subjects->map(fn ($s) => optional($s->subject)->name . ' ≥ ' . $fmt($s->min_percent))->implode(', ');

        return $text . ($criterion->min_aggregate !== null ? ' · aggregate ≥ ' . $fmt($criterion->min_aggregate) : '');
    }
}
