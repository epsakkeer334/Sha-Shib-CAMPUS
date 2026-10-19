<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\Course;
use App\Models\Admin\CourseEligibilityCriterion;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\QualifyingSubjectGroup;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Master Data → Course Eligibility (Module 2 step 2.7, Super Admin only).
 * Per course: accepted 12th subject group(s), for one board or any board, with a minimum % per
 * subject and an optional minimum aggregate. Students see the criteria when they choose the course.
 */
class CourseEligibilityManager extends Component
{
    use RecordsAuditTrail;

    // filters
    public $courseFilter = '';
    public $search = '';

    // form
    public $recordId = null;
    public $course_id, $higher_secondary_board_id = '', $qualifying_subject_group_id, $min_aggregate, $remarks;
    public $status = 1;
    /** @var array<int|string, mixed> subject id => minimum % (blank = not required) */
    public $minimums = [];

    public $confirmingDeleteId = null;

    protected $queryString = ['courseFilter' => ['except' => '', 'as' => 'course']];

    public function mount()
    {
        $this->authorizeMasters();
    }

    public function hydrate()
    {
        $this->authorizeMasters();
    }

    protected function authorizeMasters()
    {
        abort_unless(Auth::user() && Auth::user()->isSuperAdmin() && Auth::user()->can('masters.manage'), 403);
    }

    // ------------------------------------------------------------------ form

    public function openModal($courseId = null)
    {
        $this->resetValidation();
        $this->resetForm();
        $this->course_id = $courseId ?: ($this->courseFilter ?: null);
        $this->dispatchBrowserEvent('open-eligibility-modal');
    }

    protected function resetForm()
    {
        $this->reset(['recordId', 'course_id', 'higher_secondary_board_id', 'qualifying_subject_group_id', 'min_aggregate', 'remarks', 'minimums']);
        $this->status = 1;
    }

    public function edit($id)
    {
        $criterion = CourseEligibilityCriterion::with('subjects')->findOrFail($id);
        $this->resetValidation();
        $this->recordId = $criterion->id;
        $this->course_id = $criterion->course_id;
        $this->higher_secondary_board_id = $criterion->higher_secondary_board_id ?: '';
        $this->qualifying_subject_group_id = $criterion->qualifying_subject_group_id;
        $this->min_aggregate = $criterion->min_aggregate !== null ? $this->plain($criterion->min_aggregate) : null;
        $this->remarks = $criterion->remarks;
        $this->status = $criterion->status ? 1 : 0;
        $this->minimums = $criterion->subjects->mapWithKeys(fn ($s) => [$s->qualifying_subject_id => $this->plain($s->min_percent)])->all();
        $this->dispatchBrowserEvent('open-eligibility-modal');
    }

    protected function plain($value): string
    {
        return rtrim(rtrim((string) $value, '0'), '.');
    }

    /** A new group: start every subject of the group at 0 so the Super Admin only types the minimums. */
    public function updatedQualifyingSubjectGroupId($value)
    {
        $group = $value ? QualifyingSubjectGroup::with('subjects')->find($value) : null;
        $old = $this->minimums;
        $this->minimums = $group ? $group->subjects->mapWithKeys(fn ($s) => [$s->id => $old[$s->id] ?? ''])->all() : [];
    }

    protected function groupSubjects()
    {
        $group = $this->qualifying_subject_group_id ? QualifyingSubjectGroup::with('subjects')->find($this->qualifying_subject_group_id) : null;

        return $group ? $group->subjects : collect();
    }

    public function save()
    {
        $subjects = $this->groupSubjects();
        $rules = [
            'course_id' => ['required', Rule::exists('courses', 'id')->whereNull('deleted_at')],
            'higher_secondary_board_id' => ['nullable', Rule::exists('higher_secondary_boards', 'id')->whereNull('deleted_at')],
            'qualifying_subject_group_id' => ['required', Rule::exists('qualifying_subject_groups', 'id')->whereNull('deleted_at')],
            'min_aggregate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'status' => ['boolean'],
        ];
        $messages = [];
        foreach ($subjects as $subject) {
            $rules["minimums.{$subject->id}"] = ['nullable', 'numeric', 'min:0', 'max:100'];
            $messages["minimums.{$subject->id}.max"] = "{$subject->name}: between 0 and 100.";
            $messages["minimums.{$subject->id}.min"] = "{$subject->name}: between 0 and 100.";
            $messages["minimums.{$subject->id}.numeric"] = "{$subject->name}: enter a number.";
        }
        $this->validate($rules, $messages, [
            'course_id' => 'course', 'higher_secondary_board_id' => 'board', 'qualifying_subject_group_id' => '12th subject group', 'min_aggregate' => 'minimum aggregate',
        ]);

        $minimums = $subjects->filter(fn ($s) => isset($this->minimums[$s->id]) && $this->minimums[$s->id] !== '' && $this->minimums[$s->id] !== null)
            ->mapWithKeys(fn ($s) => [$s->id => round((float) $this->minimums[$s->id], 2)]);
        if ($minimums->isEmpty()) {
            $this->addError('minimums', 'Enter the minimum % for at least one subject.');

            return;
        }

        $boardId = $this->higher_secondary_board_id ?: null;
        $duplicate = CourseEligibilityCriterion::where('course_id', $this->course_id)
            ->where('qualifying_subject_group_id', $this->qualifying_subject_group_id)
            ->when($boardId, fn ($q) => $q->where('higher_secondary_board_id', $boardId), fn ($q) => $q->whereNull('higher_secondary_board_id'))
            ->when($this->recordId, fn ($q) => $q->whereKeyNot($this->recordId))
            ->exists();
        if ($duplicate) {
            $this->addError('qualifying_subject_group_id', 'This course already has a criterion for this group and board. Edit that one instead.');

            return;
        }

        $data = [
            'course_id' => (int) $this->course_id,
            'higher_secondary_board_id' => $boardId,
            'qualifying_subject_group_id' => (int) $this->qualifying_subject_group_id,
            'min_aggregate' => $this->min_aggregate === '' || $this->min_aggregate === null ? null : round((float) $this->min_aggregate, 2),
            'remarks' => trim((string) $this->remarks) ?: null,
            'status' => (bool) $this->status,
        ];

        $criterion = DB::transaction(function () use ($data, $minimums) {
            $criterion = $this->recordId ? CourseEligibilityCriterion::findOrFail($this->recordId) : new CourseEligibilityCriterion();
            $old = $criterion->exists ? $this->describe($criterion->load('subjects')) : null;
            $criterion->fill($data)->save();
            $criterion->subjects()->whereNotIn('qualifying_subject_id', $minimums->keys())->delete();
            foreach ($minimums as $subjectId => $min) {
                $criterion->subjects()->updateOrCreate(['qualifying_subject_id' => $subjectId], ['min_percent' => $min]);
            }
            $criterion->load('course', 'group', 'board', 'subjects');

            $label = "{$criterion->course->name} · {$criterion->group->code} · {$criterion->board_label}";
            if ($old) {
                $this->auditUpdate($criterion, 'course_eligibility', ['criteria' => $old], ['criteria' => $this->describe($criterion)], "Updated course eligibility: {$label}");
            } else {
                $this->auditCreate($criterion, 'course_eligibility', "Added course eligibility: {$label} — " . $this->describe($criterion));
            }

            return $criterion;
        });

        $this->courseFilter = $this->courseFilter ? (string) $criterion->course_id : '';
        $this->dispatchBrowserEvent('close-eligibility-modal');
        $this->toast('success', $this->recordId ? 'Eligibility criterion updated.' : 'Eligibility criterion added.');
        $this->resetForm();
    }

    protected function describe(CourseEligibilityCriterion $criterion): string
    {
        return \App\Services\EligibilityService::describe($criterion) . ($criterion->status ? '' : ' (inactive)');
    }

    public function toggleStatus($id)
    {
        $criterion = CourseEligibilityCriterion::findOrFail($id);
        $criterion->update(['status' => !$criterion->status]);
        $this->auditUpdate($criterion, 'course_eligibility', ['status' => !$criterion->status], ['status' => $criterion->status],
            ($criterion->status ? 'Activated' : 'Deactivated') . " course eligibility: {$criterion->course->name} · {$criterion->group->code}");
        $this->toast('success', $criterion->status ? 'Criterion active.' : 'Criterion inactive — students of this course are no longer checked against it.');
    }

    public function confirmDelete($id)
    {
        $this->confirmingDeleteId = $id;
        $this->dispatchBrowserEvent('open-eligibility-delete-modal');
    }

    public function delete()
    {
        $criterion = CourseEligibilityCriterion::with('course', 'group', 'board', 'subjects')->find($this->confirmingDeleteId);
        if ($criterion) {
            $this->auditDelete($criterion, 'course_eligibility', "Deleted course eligibility: {$criterion->course->name} · {$criterion->group->code} · {$criterion->board_label} — " . $this->describe($criterion));
            $criterion->delete();
            $this->toast('danger', 'Eligibility criterion deleted.');
        }
        $this->confirmingDeleteId = null;
        $this->dispatchBrowserEvent('close-eligibility-delete-modal');
    }

    protected function toast($type, $message)
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }

    public function render()
    {
        $criteria = CourseEligibilityCriterion::with(['course', 'board', 'group', 'subjects'])
            ->when($this->courseFilter, fn ($q) => $q->where('course_id', $this->courseFilter))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w->whereHas('course', fn ($c) => $c->where('name', 'like', $term)->orWhere('code', 'like', $term))
                    ->orWhereHas('group', fn ($g) => $g->where('code', 'like', $term)->orWhere('name', 'like', $term)));
            })
            ->get()
            ->sortBy(fn ($c) => [optional($c->course)->name, optional($c->group)->code, $c->higher_secondary_board_id ? 0 : 1])
            ->groupBy('course_id');

        $courses = Course::orderBy('name')->get(['id', 'name', 'code', 'status']);
        $withCriteria = CourseEligibilityCriterion::where('status', true)->distinct()->count('course_id');

        return view('livewire.admin.masters.course-eligibility', [
            'criteria' => $criteria,
            'courses' => $courses,
            'boards' => HigherSecondaryBoard::active()->orderBy('name')->pluck('name', 'id'),
            'groups' => QualifyingSubjectGroup::where(fn ($q) => $q->where('status', true)->orWhere('id', $this->qualifying_subject_group_id))->orderBy('code')->get(['id', 'code', 'name']),
            'groupSubjects' => $this->groupSubjects(),
            'stats' => [
                'courses' => $courses->where('status', true)->count(),
                'with' => $withCriteria,
                'criteria' => CourseEligibilityCriterion::count(),
                'groups' => QualifyingSubjectGroup::active()->count(),
            ],
        ])->layout('layouts.admin.master');
    }
}
