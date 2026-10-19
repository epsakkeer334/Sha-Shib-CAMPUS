<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\Course;
use App\Models\Admin\CourseEligibilityCriterion;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\QualifyingSubjectGroup;
use App\Services\EligibilityService;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Master Data → Course Eligibility (Module 2 step 2.7, Super Admin only).
 * Per course: accepted 12th subject group(s), for one board or any board, with a minimum % per
 * subject and an optional minimum aggregate. Students see the criteria when they choose the course.
 *
 * Quick set-up: one save covers several courses × several streams, a "same minimum for every subject"
 * fill, and "Copy to other courses" from a configured course. Saving a course + stream + board that
 * already exists updates it (no duplicates).
 */
class CourseEligibilityManager extends Component
{
    use RecordsAuditTrail;

    // filters
    public $courseFilter = '';
    public $search = '';

    // form (add: many courses × many groups; edit: one criterion)
    public $recordId = null;
    public $courseIds = [];
    public $groupIds = [];
    public $higher_secondary_board_id = '';
    /** @var array<int|string, array<int|string, mixed>> group id => [subject id => minimum %] (blank = not required) */
    public $minimums = [];
    /** @var array<int|string, mixed> group id => minimum average % */
    public $aggregates = [];
    public $remarks;
    public $status = 1;
    public $quickMin = '50';
    public $quickAgg = '';
    public $courseSearch = '';

    // copy to other courses
    public $copyFromId = null;
    public $copyToIds = [];

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

    // ------------------------------------------------------------------ add / edit

    public function openModal($courseId = null)
    {
        $this->resetValidation();
        $this->resetForm();
        $course = $courseId ?: ($this->courseFilter ?: null);
        $this->courseIds = $course ? [(string) $course] : [];
        $this->dispatchBrowserEvent('open-eligibility-modal');
    }

    protected function resetForm()
    {
        $this->reset(['recordId', 'courseIds', 'groupIds', 'higher_secondary_board_id', 'minimums', 'aggregates', 'remarks', 'courseSearch', 'quickAgg']);
        $this->status = 1;
        $this->quickMin = '50';
    }

    public function edit($id)
    {
        $criterion = CourseEligibilityCriterion::with('subjects')->findOrFail($id);
        $this->resetValidation();
        $this->resetForm();
        $group = (string) $criterion->qualifying_subject_group_id;
        $this->recordId = $criterion->id;
        $this->courseIds = [(string) $criterion->course_id];
        $this->groupIds = [$group];
        $this->higher_secondary_board_id = $criterion->higher_secondary_board_id ?: '';
        $this->minimums = [$group => $criterion->subjects->mapWithKeys(fn ($s) => [$s->qualifying_subject_id => $this->plain($s->min_percent)])->all()];
        $this->aggregates = [$group => $criterion->min_aggregate !== null ? $this->plain($criterion->min_aggregate) : ''];
        $this->remarks = $criterion->remarks;
        $this->status = $criterion->status ? 1 : 0;
        $this->dispatchBrowserEvent('open-eligibility-modal');
    }

    protected function plain($value): string
    {
        return rtrim(rtrim((string) $value, '0'), '.');
    }

    /** Tick / untick a stream; a newly ticked stream gets the quick minimum in every subject. */
    public function toggleGroup($groupId)
    {
        $groupId = (string) $groupId;
        if (in_array($groupId, $this->groupIds, true)) {
            $this->groupIds = array_values(array_diff($this->groupIds, [$groupId]));

            return;
        }
        $this->groupIds[] = $groupId;
        $group = QualifyingSubjectGroup::with('subjects')->find($groupId);
        if ($group && empty($this->minimums[$groupId])) {
            $this->minimums[$groupId] = $group->subjects->mapWithKeys(fn ($s) => [$s->id => (string) $this->quickMin])->all();
            $this->aggregates[$groupId] = (string) $this->quickAgg;
        }
    }

    public function toggleCourse($courseId)
    {
        $courseId = (string) $courseId;
        $this->courseIds = in_array($courseId, $this->courseIds, true)
            ? array_values(array_diff($this->courseIds, [$courseId]))
            : array_merge($this->courseIds, [$courseId]);
    }

    /** "Apply to all": the same minimum (and average) for every subject of every ticked stream. */
    public function applyQuick()
    {
        $this->validate(['quickMin' => ['nullable', 'numeric', 'min:0', 'max:100'], 'quickAgg' => ['nullable', 'numeric', 'min:0', 'max:100']], [], ['quickMin' => 'minimum', 'quickAgg' => 'average']);
        foreach ($this->selectedGroups() as $group) {
            $this->minimums[$group->id] = $group->subjects->mapWithKeys(fn ($s) => [$s->id => (string) $this->quickMin])->all();
            $this->aggregates[$group->id] = (string) $this->quickAgg;
        }
    }

    protected function selectedGroups()
    {
        if (!$this->groupIds) {
            return collect();
        }
        $order = array_flip($this->groupIds);

        return QualifyingSubjectGroup::with('subjects')->whereIn('id', $this->groupIds)->get()->sortBy(fn ($g) => $order[(string) $g->id] ?? 99)->values();
    }

    public function save()
    {
        $groups = $this->selectedGroups();
        $rules = [
            'courseIds' => ['required', 'array', 'min:1'],
            'courseIds.*' => [Rule::exists('courses', 'id')->whereNull('deleted_at')],
            'groupIds' => ['required', 'array', 'min:1'],
            'groupIds.*' => [Rule::exists('qualifying_subject_groups', 'id')->whereNull('deleted_at')],
            'higher_secondary_board_id' => ['nullable', Rule::exists('higher_secondary_boards', 'id')->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:500'],
            'status' => ['boolean'],
        ];
        $messages = ['courseIds.required' => 'Choose at least one course.', 'groupIds.required' => 'Tick at least one 12th stream.'];
        foreach ($groups as $group) {
            $rules["aggregates.{$group->id}"] = ['nullable', 'numeric', 'min:0', 'max:100'];
            $messages["aggregates.{$group->id}.*"] = "{$group->code} average: a number between 0 and 100.";
            foreach ($group->subjects as $subject) {
                $rules["minimums.{$group->id}.{$subject->id}"] = ['nullable', 'numeric', 'min:0', 'max:100'];
                $messages["minimums.{$group->id}.{$subject->id}.*"] = "{$group->code} · {$subject->name}: a number between 0 and 100.";
            }
        }
        $this->validate($rules, $messages, ['higher_secondary_board_id' => 'board']);

        // Minimums per ticked stream (at least one subject each)
        $plan = [];
        foreach ($groups as $group) {
            $mins = $group->subjects
                ->filter(fn ($s) => isset($this->minimums[$group->id][$s->id]) && $this->minimums[$group->id][$s->id] !== '' && $this->minimums[$group->id][$s->id] !== null)
                ->mapWithKeys(fn ($s) => [$s->id => round((float) $this->minimums[$group->id][$s->id], 2)]);
            if ($mins->isEmpty()) {
                $this->addError("minimums.{$group->id}", "{$group->code}: enter the minimum % for at least one subject.");

                return;
            }
            $agg = $this->aggregates[$group->id] ?? '';
            $plan[$group->id] = ['mins' => $mins, 'agg' => $agg === '' || $agg === null ? null : round((float) $agg, 2)];
        }

        $boardId = $this->higher_secondary_board_id ?: null;
        $counts = ['new' => 0, 'updated' => 0];

        DB::transaction(function () use ($plan, $boardId, &$counts) {
            foreach (array_unique($this->courseIds) as $courseId) {
                foreach ($plan as $groupId => $p) {
                    $criterion = $this->recordId && count($this->courseIds) === 1
                        ? CourseEligibilityCriterion::findOrFail($this->recordId)
                        : CourseEligibilityCriterion::firstOrNew(['course_id' => (int) $courseId, 'qualifying_subject_group_id' => (int) $groupId, 'higher_secondary_board_id' => $boardId]);
                    $this->store($criterion, (int) $courseId, (int) $groupId, $boardId, $p['mins'], $p['agg'], $counts);
                }
            }
        });

        $this->dispatchBrowserEvent('close-eligibility-modal');
        $total = $counts['new'] + $counts['updated'];
        $this->toast('success', $this->recordId ? 'Eligibility criterion updated.'
            : "Saved {$total} " . ($total === 1 ? 'criterion' : 'criteria') . " ({$counts['new']} new, {$counts['updated']} updated).");
        $this->resetForm();
    }

    /** Create or update one criterion (and its subject minimums), audited. A board change that hits another row is refused. */
    protected function store(CourseEligibilityCriterion $criterion, int $courseId, int $groupId, $boardId, $mins, $agg, array &$counts): void
    {
        if ($criterion->exists && $this->recordId) {
            $clash = CourseEligibilityCriterion::where('course_id', $courseId)->where('qualifying_subject_group_id', $groupId)
                ->when($boardId, fn ($q) => $q->where('higher_secondary_board_id', $boardId), fn ($q) => $q->whereNull('higher_secondary_board_id'))
                ->whereKeyNot($criterion->id)->exists();
            if ($clash) {
                throw \Illuminate\Validation\ValidationException::withMessages(['higher_secondary_board_id' => 'This course already has this stream for that board. Edit that one instead.']);
            }
        }

        $old = $criterion->exists ? EligibilityService::describe($criterion->load('subjects')) : null;
        $criterion->fill([
            'course_id' => $courseId, 'qualifying_subject_group_id' => $groupId, 'higher_secondary_board_id' => $boardId,
            'min_aggregate' => $agg, 'remarks' => trim((string) $this->remarks) ?: null, 'status' => (bool) $this->status,
        ])->save();
        $criterion->subjects()->whereNotIn('qualifying_subject_id', $mins->keys())->delete();
        foreach ($mins as $subjectId => $min) {
            $criterion->subjects()->updateOrCreate(['qualifying_subject_id' => $subjectId], ['min_percent' => $min]);
        }
        $criterion->load('course', 'group', 'board', 'subjects');
        $label = "{$criterion->course->name} · {$criterion->group->code} · {$criterion->board_label}";

        if ($old !== null) {
            $this->auditUpdate($criterion, 'course_eligibility', ['criteria' => $old], ['criteria' => EligibilityService::describe($criterion)], "Updated course eligibility: {$label}");
            $counts['updated']++;
        } else {
            $this->auditCreate($criterion, 'course_eligibility', "Added course eligibility: {$label} — " . EligibilityService::describe($criterion));
            $counts['new']++;
        }
    }

    // ------------------------------------------------------------------ copy to other courses

    public function openCopy($courseId)
    {
        $this->resetValidation();
        $this->copyFromId = (int) $courseId;
        $this->copyToIds = [];
        $this->courseSearch = '';
        $this->dispatchBrowserEvent('open-eligibility-copy-modal');
    }

    public function toggleCopyTo($courseId)
    {
        $courseId = (string) $courseId;
        $this->copyToIds = in_array($courseId, $this->copyToIds, true)
            ? array_values(array_diff($this->copyToIds, [$courseId]))
            : array_merge($this->copyToIds, [$courseId]);
    }

    public function copy()
    {
        $this->validate(['copyToIds' => ['required', 'array', 'min:1'], 'copyToIds.*' => [Rule::exists('courses', 'id')->whereNull('deleted_at')]],
            ['copyToIds.required' => 'Choose at least one course to copy to.']);

        $source = CourseEligibilityCriterion::with('subjects')->where('course_id', $this->copyFromId)->get();
        $counts = ['new' => 0, 'updated' => 0];
        DB::transaction(function () use ($source, &$counts) {
            foreach (array_unique($this->copyToIds) as $courseId) {
                if ((int) $courseId === (int) $this->copyFromId) {
                    continue;
                }
                foreach ($source as $from) {
                    $criterion = CourseEligibilityCriterion::firstOrNew([
                        'course_id' => (int) $courseId, 'qualifying_subject_group_id' => $from->qualifying_subject_group_id, 'higher_secondary_board_id' => $from->higher_secondary_board_id,
                    ]);
                    $this->remarks = $from->remarks;
                    $this->status = $from->status ? 1 : 0;
                    $mins = $from->subjects->mapWithKeys(fn ($s) => [$s->qualifying_subject_id => (float) $s->min_percent]);
                    $this->store($criterion, (int) $courseId, $from->qualifying_subject_group_id, $from->higher_secondary_board_id, $mins,
                        $from->min_aggregate !== null ? (float) $from->min_aggregate : null, $counts);
                }
            }
        });

        $this->remarks = null;
        $this->status = 1;
        $this->dispatchBrowserEvent('close-eligibility-copy-modal');
        $this->toast('success', "Copied: {$counts['new']} new, {$counts['updated']} updated.");
        $this->copyToIds = [];
    }

    // ------------------------------------------------------------------ status / delete

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
            $this->auditDelete($criterion, 'course_eligibility', "Deleted course eligibility: {$criterion->course->name} · {$criterion->group->code} · {$criterion->board_label} — " . EligibilityService::describe($criterion));
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
        $configured = CourseEligibilityCriterion::distinct()->pluck('course_id')->all();
        $term = mb_strtolower(trim($this->courseSearch));
        $pickable = $courses->filter(fn ($c) => $term === '' || str_contains(mb_strtolower("{$c->name} {$c->code}"), $term));

        return view('livewire.admin.masters.course-eligibility', [
            'criteria' => $criteria,
            'courses' => $courses,
            'pickable' => $pickable,
            'configured' => $configured,
            'boards' => HigherSecondaryBoard::active()->orderBy('name')->pluck('name', 'id'),
            'groups' => QualifyingSubjectGroup::with('subjects')->where(fn ($q) => $q->where('status', true)->orWhereIn('id', $this->groupIds ?: [0]))->orderBy('code')->get(),
            'selectedGroups' => $this->selectedGroups(),
            'copyFrom' => $this->copyFromId ? $courses->firstWhere('id', $this->copyFromId) : null,
            'stats' => [
                'courses' => $courses->where('status', true)->count(),
                'with' => CourseEligibilityCriterion::where('status', true)->distinct()->count('course_id'),
                'criteria' => CourseEligibilityCriterion::count(),
                'groups' => QualifyingSubjectGroup::active()->count(),
            ],
        ])->layout('layouts.admin.master');
    }
}
