<?php

namespace App\Http\Livewire\Admin\Scholarships;

use App\Models\Admin\AcademicYear;
use App\Models\Admin\Batch;
use App\Models\Admin\Course;
use App\Models\Admin\Institute;
use App\Models\Admin\Scholarship;
use App\Models\Admin\StudentDueDiscount;
use App\Models\Admin\StudentScholarship;
use App\Services\ScholarshipService;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Scholarships ▸ Scholarships (Module 2C): the schemes of an institute — course (+ batch / academic year),
 * percentage or fixed amount per semester, semesters covered. Super Admin: all institutes; Institute Admin:
 * own institute (scholarships.manage); Accounts: view only (scholarships.view).
 */
class ScholarshipsComponent extends Component
{
    use RecordsAuditTrail;

    // filters
    public $search = '';
    public $institute = '';
    public $course = '';
    public $state = 'active'; // active | inactive | all

    // form
    public $recordId = null;
    public $institute_id, $course_id, $batch_id = '', $academic_year_id = '';
    public $name, $code, $description;
    public $type = 'percentage';
    public $value;
    public $applies_to = 'period_fees';
    public $periods = [];
    public $status = 1;

    public $confirmingDeleteId = null;

    protected $queryString = ['search' => ['except' => ''], 'course' => ['except' => ''], 'state' => ['except' => 'active']];

    public function mount()
    {
        abort_unless(Auth::user()->can('scholarships.view'), 403);
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('scholarships.view'), 403);
    }

    protected function authorizeManage()
    {
        abort_unless(Auth::user()->can('scholarships.manage'), 403);
    }

    // ------------------------------------------------------------------ form

    public function openModal()
    {
        $this->authorizeManage();
        $this->resetValidation();
        $this->resetForm();
        $this->institute_id = Auth::user()->isSuperAdmin() ? ($this->institute ?: null) : Auth::user()->institute_id;
        $this->course_id = $this->course ?: null;
        $this->dispatchBrowserEvent('open-scholarship-modal');
    }

    protected function resetForm()
    {
        $this->reset(['recordId', 'institute_id', 'course_id', 'batch_id', 'academic_year_id', 'name', 'code', 'description', 'value', 'periods']);
        $this->type = 'percentage';
        $this->applies_to = 'period_fees';
        $this->status = 1;
    }

    public function edit($id)
    {
        $this->authorizeManage();
        $s = Scholarship::with('periodRows')->findOrFail($id);
        $this->resetValidation();
        $this->recordId = $s->id;
        $this->fill($s->only(['institute_id', 'course_id', 'name', 'code', 'description', 'type', 'applies_to']));
        $this->batch_id = $s->batch_id ?: '';
        $this->academic_year_id = $s->academic_year_id ?: '';
        $this->value = rtrim(rtrim((string) $s->value, '0'), '.');
        $this->periods = array_map('strval', $s->periods);
        $this->status = $s->status ? 1 : 0;
        $this->dispatchBrowserEvent('open-scholarship-modal');
    }

    public function updatedInstituteId()
    {
        $this->course_id = null;
        $this->batch_id = '';
        $this->periods = [];
    }

    public function updatedCourseId()
    {
        $this->batch_id = '';
        $this->periods = array_values(array_filter($this->periods, fn ($p) => (int) $p <= $this->totalPeriods()));
    }

    public function setType($type)
    {
        if (array_key_exists($type, Scholarship::TYPES)) {
            $this->type = $type;
        }
    }

    public function togglePeriod($n)
    {
        $n = (string) (int) $n;
        $this->periods = in_array($n, $this->periods, true) ? array_values(array_diff($this->periods, [$n])) : array_merge($this->periods, [$n]);
    }

    public function allPeriods()
    {
        $all = array_map('strval', range(1, $this->totalPeriods()));
        $this->periods = count($this->periods) === count($all) ? [] : $all;
    }

    protected function totalPeriods(): int
    {
        return max(1, (int) optional(Course::find($this->course_id))->total_periods);
    }

    public function save()
    {
        $this->authorizeManage();
        if (!Auth::user()->isSuperAdmin()) {
            $this->institute_id = Auth::user()->institute_id;
        }
        $this->code = strtoupper(trim((string) $this->code));
        $total = $this->totalPeriods();

        $this->validate([
            'institute_id' => ['required', Rule::exists('institutes', 'id')->whereNull('deleted_at')],
            'course_id' => ['required', Rule::exists('institute_courses', 'course_id')->where('institute_id', $this->institute_id)->whereNull('deleted_at')],
            'batch_id' => ['nullable', Rule::exists('batches', 'id')->where('institute_id', $this->institute_id)->where('course_id', $this->course_id)->whereNull('deleted_at')],
            'academic_year_id' => ['nullable', Rule::exists('academic_years', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('scholarships', 'code')->where('institute_id', $this->institute_id)->whereNull('deleted_at')->ignore($this->recordId)],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(array_keys(Scholarship::TYPES))],
            'value' => ['required', 'numeric', 'gt:0', $this->type === 'percentage' ? 'max:100' : 'max:9999999'],
            'applies_to' => ['required', Rule::in(array_keys(Scholarship::APPLIES_TO))],
            'periods' => ['required', 'array', 'min:1'],
            'periods.*' => ['integer', 'min:1', "max:{$total}"],
        ], [
            'course_id.exists' => 'This course is not offered by the institute.',
            'code.regex' => 'Use capital letters, digits, - or _ (e.g. MERIT25).',
            'periods.required' => 'Choose at least one semester.',
            'value.max' => $this->type === 'percentage' ? 'A percentage cannot be more than 100.' : 'The amount is too large.',
        ], ['course_id' => 'course', 'batch_id' => 'batch', 'academic_year_id' => 'academic year', 'periods' => 'semesters']);

        $data = [
            'institute_id' => (int) $this->institute_id, 'course_id' => (int) $this->course_id,
            'batch_id' => $this->batch_id ?: null, 'academic_year_id' => $this->academic_year_id ?: null,
            'name' => trim($this->name), 'code' => $this->code, 'description' => trim((string) $this->description) ?: null,
            'type' => $this->type, 'value' => round((float) $this->value, 2), 'applies_to' => $this->applies_to, 'status' => (bool) $this->status,
        ];
        $periods = collect($this->periods)->map(fn ($p) => (int) $p)->unique()->sort()->values();

        $reapplied = 0;
        DB::transaction(function () use ($data, $periods, &$reapplied) {
            $s = $this->recordId ? Scholarship::with('periodRows')->findOrFail($this->recordId) : new Scholarship();
            $before = $s->exists ? $s->only(['type', 'value', 'applies_to', 'batch_id', 'academic_year_id']) + ['periods' => $s->periods] : null;
            $s->fill($data)->save();
            $s->periodRows()->whereNotIn('period_no', $periods)->delete();
            foreach ($periods as $p) {
                $s->periodRows()->firstOrCreate(['period_no' => $p]);
            }
            $s->load('periodRows', 'course');

            if ($before === null) {
                $this->auditCreate($s, 'scholarships', "Created scholarship {$s->name} ({$s->code}) — {$s->value_label}, {$s->periods_label}");
            } else {
                $after = $s->only(['type', 'value', 'applies_to', 'batch_id', 'academic_year_id']) + ['periods' => $s->periods];
                $this->auditUpdate($s, 'scholarships', $before, $after, "Updated scholarship {$s->name} ({$s->code})");
                if ($before != $after) {
                    $reapplied = app(ScholarshipService::class)->reapply($s); // unpaid dues follow the new terms
                }
            }
        });

        $this->dispatchBrowserEvent('close-scholarship-modal');
        $this->toast('success', ($this->recordId ? 'Scholarship updated.' : 'Scholarship created.') . ($reapplied ? " Discounts recalculated for {$reapplied} " . ($reapplied === 1 ? 'student' : 'students') . ' (unpaid fees only).' : ''));
        $this->resetForm();
    }

    public function toggleStatus($id)
    {
        $this->authorizeManage();
        $s = Scholarship::findOrFail($id);
        $s->update(['status' => !$s->status]);
        $this->auditUpdate($s, 'scholarships', ['status' => !$s->status], ['status' => $s->status], ($s->status ? 'Activated' : 'Deactivated') . " scholarship {$s->name}");
        $this->toast('success', $s->status ? 'Scholarship active — it can be assigned.' : 'Scholarship inactive — no new assignments; students who have it keep it.');
    }

    public function confirmDelete($id)
    {
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
        $this->dispatchBrowserEvent('open-scholarship-delete-modal');
    }

    public function delete()
    {
        $this->authorizeManage();
        $s = Scholarship::find($this->confirmingDeleteId);
        if ($s && StudentScholarship::withoutGlobalScopes()->where('scholarship_id', $s->id)->exists()) {
            $this->toast('warning', 'This scholarship has been given to students, so it cannot be deleted. Set it inactive instead.');
        } elseif ($s) {
            $this->auditDelete($s, 'scholarships', "Deleted scholarship {$s->name} ({$s->code})");
            $s->delete();
            $this->toast('danger', 'Scholarship deleted.');
        }
        $this->confirmingDeleteId = null;
        $this->dispatchBrowserEvent('close-scholarship-delete-modal');
    }

    protected function toast($type, $message)
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }

    public function render()
    {
        $user = Auth::user();
        $isSuper = $user->isSuperAdmin();

        $scholarships = Scholarship::with(['course', 'batch', 'academicYear', 'periodRows', 'institute'])
            ->withCount(['assignments as students_count' => fn ($q) => $q->where('status', 'active')])
            ->when($isSuper && $this->institute, fn ($q) => $q->where('institute_id', $this->institute))
            ->when($this->course, fn ($q) => $q->where('course_id', $this->course))
            ->when($this->state !== 'all', fn ($q) => $q->where('status', $this->state === 'active'))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%' . trim($this->search) . '%')->orWhere('code', 'like', '%' . trim($this->search) . '%')))
            ->orderByDesc('status')->orderBy('name')->get();

        $given = StudentDueDiscount::query()
            ->join('student_scholarships', 'student_scholarships.id', '=', 'student_due_discounts.student_scholarship_id')
            ->when(!$isSuper, fn ($q) => $q->where('student_scholarships.institute_id', $user->institute_id))
            ->select('student_scholarships.scholarship_id', DB::raw('SUM(student_due_discounts.amount) as total'))
            ->groupBy('student_scholarships.scholarship_id')->pluck('total', 'scholarship_id');

        $formInstitute = $isSuper ? $this->institute_id : $user->institute_id;
        $formCourses = $formInstitute ? (optional(Institute::find($formInstitute))->offeredCourses()?->orderBy('name')->get(['courses.id', 'courses.name', 'courses.code', 'courses.total_semesters', 'courses.period_label']) ?? collect()) : collect();
        $filterInstitute = $isSuper ? $this->institute : $user->institute_id;
        $filterCourses = $filterInstitute
            ? (optional(Institute::find($filterInstitute))->offeredCourses()?->orderBy('name')->get(['courses.id', 'courses.name', 'courses.code']) ?? collect())
            : Course::orderBy('name')->get(['id', 'name', 'code']);
        $formCourse = $this->course_id ? Course::find($this->course_id) : null;

        return view('livewire.admin.scholarships.scholarships-component', [
            'scholarships' => $scholarships,
            'given' => $given,
            'isSuperAdmin' => $isSuper,
            'canManage' => $user->can('scholarships.manage'),
            'canAssign' => $user->can('scholarships.assign'),
            'institutes' => $isSuper ? Institute::orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'filterCourses' => $filterCourses,
            'formCourses' => $formCourses,
            'formCourse' => $formCourse,
            'formBatches' => $formInstitute && $this->course_id ? Batch::withoutGlobalScopes()->whereNull('deleted_at')->where('institute_id', $formInstitute)->where('course_id', $this->course_id)->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'years' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']),
            'stats' => [
                'active' => $scholarships->where('status', true)->count(),
                'students' => StudentScholarship::active()->count(),
                'given' => (float) $given->sum(),
            ],
        ])->layout('layouts.admin.master');
    }
}
