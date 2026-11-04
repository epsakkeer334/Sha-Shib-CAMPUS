<?php

namespace App\Http\Livewire\Admin\Institutes;

use App\Models\Admin\AddonProgram;
use App\Models\Admin\Course;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\StudentAddonProgram;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Institute Management → Add-on Programs (Module 2 step 2.10): optional extras per institute course that
 * applicants can choose at registration, with an optional fee and seats, and a choice limit per course.
 * Super Admin: every institute; Institute Admin (addon_programs.manage): own institute only.
 */
class AddonProgramsComponent extends Component
{
    use RecordsAuditTrail;

    // filters
    public $search = '';
    public $filterInstitute = '';
    public $filterCourse = '';

    protected $queryString = [
        'search' => ['except' => ''], 'filterInstitute' => ['except' => '', 'as' => 'institute'], 'filterCourse' => ['except' => '', 'as' => 'course'],
    ];

    // form (modal)
    public $editingId = null;
    public $instituteId = null, $courseId = null;
    public $name, $code, $description, $fee_amount, $fee_due_days = 0, $capacity, $sort_order = 0;
    public $status = 1;

    public $confirmingDeleteId = null;

    public function mount()
    {
        $this->authorizeAddons();
        $this->instituteId = Auth::user()->isSuperAdmin() ? null : Auth::user()->institute_id;
    }

    public function hydrate()
    {
        $this->authorizeAddons();
        if (!Auth::user()->isSuperAdmin()) {
            $this->instituteId = Auth::user()->institute_id; // own institute only, whatever the browser sends
            $this->filterInstitute = '';
        }
    }

    protected function authorizeAddons()
    {
        abort_unless(Auth::user() && Auth::user()->can('addon_programs.manage'), 403);
    }

    public function updated($property)
    {
        if ($property === 'filterInstitute') {
            $this->filterCourse = '';
        }
        if ($property === 'instituteId') {
            $this->courseId = null;
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'filterInstitute', 'filterCourse']);
    }

    protected function rules()
    {
        return [
            'instituteId' => ['required', Rule::exists('institutes', 'id')->whereNull('deleted_at')],
            'courseId' => ['required', function ($attribute, $value, $fail) {
                if (!InstituteCourse::where('institute_id', $this->instituteId)->where('course_id', $value)->exists()) {
                    $fail('This course is not offered by the selected institute.');
                }
            }],
            'name' => ['required', 'string', 'max:150', function ($attribute, $value, $fail) {
                $taken = AddonProgram::withoutGlobalScopes()->whereNull('deleted_at')->ofCourse($this->instituteId, $this->courseId)
                    ->where('name', trim((string) $value))->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->exists();
                if ($taken) {
                    $fail('This course already has an add-on with this name.');
                }
            }],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:500'],
            'fee_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'fee_due_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => 'boolean',
        ];
    }

    protected $validationAttributes = ['instituteId' => 'institute', 'courseId' => 'course', 'name' => 'add-on name', 'fee_amount' => 'fee', 'fee_due_days' => 'due days'];

    public function create($courseId = null, $instituteId = null)
    {
        $this->resetForm();
        if ($instituteId && Auth::user()->isSuperAdmin()) {
            $this->instituteId = (int) $instituteId;
        }
        if ($courseId) {
            $this->courseId = (int) $courseId;
        }
        $this->dispatchBrowserEvent('open-addon-modal');
    }

    public function edit($id)
    {
        $program = AddonProgram::findOrFail($id); // institute-scoped
        $this->resetValidation();
        $this->fill([
            'editingId' => $program->id, 'instituteId' => $program->institute_id, 'courseId' => $program->course_id,
            'name' => $program->name, 'code' => $program->code, 'description' => $program->description,
            'fee_amount' => $program->isFree() ? null : rtrim(rtrim((string) $program->fee_amount, '0'), '.'),
            'fee_due_days' => $program->fee_due_days, 'capacity' => $program->capacity, 'sort_order' => $program->sort_order,
            'status' => $program->status ? 1 : 0,
        ]);
        $this->dispatchBrowserEvent('open-addon-modal');
    }

    public function save()
    {
        if (!Auth::user()->isSuperAdmin()) {
            $this->instituteId = Auth::user()->institute_id;
        }
        $data = $this->validate();

        $values = [
            'institute_id' => $this->instituteId, 'course_id' => $this->courseId, 'name' => trim($data['name']),
            'code' => trim((string) $data['code']) !== '' ? strtoupper(trim($data['code'])) : null,
            'description' => trim((string) $data['description']) ?: null,
            'fee_amount' => $data['fee_amount'] !== null && $data['fee_amount'] !== '' && (float) $data['fee_amount'] > 0 ? $data['fee_amount'] : null,
            'fee_due_days' => (int) ($data['fee_due_days'] ?? 0),
            'capacity' => $data['capacity'] !== null && $data['capacity'] !== '' ? (int) $data['capacity'] : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => (bool) $data['status'],
        ];

        if ($this->editingId) {
            $program = AddonProgram::findOrFail($this->editingId);
            if ($program->choices()->exists()) {
                unset($values['institute_id'], $values['course_id']); // chosen by students: stays on its course
            }
            $old = $program->only(array_keys($values));
            $program->update($values);
            $this->auditUpdate($program, 'addon_programs', $old, $program->only(array_keys($values)), "Updated add-on program {$program->name}");
            $message = "Add-on {$program->name} updated." . ($program->choices()->exists() && array_key_exists('fee_amount', $old) && (string) $old['fee_amount'] !== (string) $program->fee_amount
                ? ' The new fee applies to students who choose it from now on.' : '');
        } else {
            $program = AddonProgram::create($values);
            $this->auditCreate($program, 'addon_programs', "Created add-on program {$program->name}");
            $message = "Add-on {$program->name} added.";
        }

        $this->dispatchBrowserEvent('close-addon-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => $message]);
        $this->resetForm();
    }

    public function toggleStatus($id)
    {
        $program = AddonProgram::findOrFail($id);
        $old = ['status' => $program->status];
        $program->update(['status' => !$program->status]);
        $this->auditUpdate($program, 'addon_programs', $old, ['status' => $program->status], ($program->status ? 'Activated' : 'Deactivated') . " add-on program {$program->name}");
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => "{$program->name} is now " . ($program->status ? 'offered to applicants.' : 'hidden from applicants.')]);
    }

    /** Choice limit of an institute course ('' = no limit). */
    public function setMax($instituteCourseId, $value)
    {
        $row = InstituteCourse::visibleTo(Auth::user())->findOrFail($instituteCourseId);
        $max = $value === '' || $value === null ? null : max(1, min(20, (int) $value));
        $old = ['max_addon_programs' => $row->max_addon_programs];
        $row->update(['max_addon_programs' => $max]);
        $this->auditUpdate($row, 'institute_courses', $old, ['max_addon_programs' => $max], 'Set add-on choice limit of ' . optional($row->course)->code . ' to ' . ($max ?? 'no limit'));
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => optional($row->course)->code . ': ' . ($max ? "applicants can choose up to {$max}." : 'no limit on add-on choices.')]);
    }

    public function confirmDelete($id)
    {
        $this->confirmingDeleteId = AddonProgram::findOrFail($id)->id;
        $this->dispatchBrowserEvent('open-addon-delete-modal');
    }

    public function delete()
    {
        $program = AddonProgram::findOrFail($this->confirmingDeleteId);
        $this->confirmingDeleteId = null;
        $this->dispatchBrowserEvent('close-addon-delete-modal');

        if ($program->choices()->exists()) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'warning', 'message' => "Students have chosen {$program->name}, so it cannot be deleted. Deactivate it instead."]);

            return;
        }

        $this->auditDelete($program, 'addon_programs', "Deleted add-on program {$program->name}");
        $program->delete();
        $this->dispatchBrowserEvent('show-toast', ['type' => 'danger', 'message' => "Add-on {$program->name} deleted."]);
    }

    protected function resetForm()
    {
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'code', 'description', 'fee_amount', 'capacity']);
        $this->fee_due_days = 0;
        $this->sort_order = 0;
        $this->status = 1;
        if (Auth::user()->isSuperAdmin()) {
            $this->instituteId = $this->filterInstitute ? (int) $this->filterInstitute : $this->instituteId;
        }
        $this->courseId = $this->filterCourse ? (int) $this->filterCourse : null;
    }

    public function render()
    {
        $user = Auth::user();
        $isSuperAdmin = $user->isSuperAdmin();
        $scopeInstitute = $isSuperAdmin ? $this->filterInstitute : $user->institute_id;
        $term = trim($this->search);

        // one card per institute course
        $instituteCourses = InstituteCourse::with(['course', 'institute'])
            ->when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute))
            ->when($this->filterCourse, fn ($q) => $q->where('course_id', $this->filterCourse))
            ->get()
            ->sortBy(fn ($ic) => (optional($ic->institute)->name ?? '') . '|' . (optional($ic->course)->code ?? ''))
            ->values();

        $programs = AddonProgram::when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute))
            ->when($this->filterCourse, fn ($q) => $q->where('course_id', $this->filterCourse))
            ->withCount(['choices as chosen_count' => fn ($q) => $q->whereHas('student', fn ($s) => $s->where('status', '!=', 'rejected'))])
            ->orderByDesc('status')->orderBy('sort_order')->orderBy('name')
            ->get()
            ->groupBy(fn ($p) => $p->institute_id . '-' . $p->course_id);

        if ($term !== '') {
            $needle = mb_strtolower($term);
            $programs = $programs->map(fn ($group) => $group->filter(fn ($p) => str_contains(mb_strtolower($p->name . ' ' . $p->code . ' ' . $p->description), $needle)));
            $instituteCourses = $instituteCourses->filter(fn ($ic) => $programs->get($ic->institute_id . '-' . $ic->course_id, collect())->isNotEmpty()
                || str_contains(mb_strtolower(optional($ic->course)->code . ' ' . optional($ic->course)->name), $needle))->values();
        }

        $all = AddonProgram::when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute));
        $courseIds = InstituteCourse::when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute))->pluck('course_id');

        return view('livewire.admin.institutes.addon-programs-component', [
            'instituteCourses' => $instituteCourses,
            'programs' => $programs,
            'stats' => [
                'total' => (clone $all)->count(),
                'active' => (clone $all)->where('status', true)->count(),
                'paid' => (clone $all)->where('fee_amount', '>', 0)->count(),
                'students' => StudentAddonProgram::whereHas('program', fn ($q) => $q->when($scopeInstitute, fn ($w) => $w->where('institute_id', $scopeInstitute)))->distinct('student_id')->count('student_id'),
            ],
            'isSuperAdmin' => $isSuperAdmin,
            'institutes' => $isSuperAdmin ? Institute::orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'filterCourses' => Course::whereIn('id', $courseIds)->orderBy('code')->get(['id', 'code', 'name']),
            'modalCourses' => $this->instituteId
                ? InstituteCourse::with('course')->where('institute_id', $this->instituteId)->get()->pluck('course')->filter()->sortBy('code')
                : collect(),
            'editingChosen' => $this->editingId ? (int) optional(AddonProgram::find($this->editingId))->choices()->count() : 0,
            'hasFilters' => $this->search || $this->filterInstitute || $this->filterCourse,
        ])->layout('layouts.admin.master');
    }
}
