<?php

namespace App\Http\Livewire\Admin\Onboarding;

use App\Models\Admin\Course;
use App\Models\Admin\CourseFee;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Services\FeeService;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Module 2.3 — fee structure per institute course (e.g. Admission fee ₹15,000 due on joining,
 * Semester 1 fee due 14 days after joining). Student dues are generated from it.
 * Every fee line is listed in one table, grouped by institute course, with a filter bar and summary
 * cards; courses offered without any fee yet are listed so they can be set up.
 * Super Admin: any institute; others: own institute.
 */
class FeeStructureComponent extends Component
{
    use RecordsAuditTrail, WithPagination;

    protected $paginationTheme = 'bootstrap';

    const PER_PAGE = 12; // courses (cards) per page — each card holds all its fee lines

    // filters
    public $search = '';
    public $filterInstitute = '';
    public $filterCourse = '';
    public $filterStatus = '';
    public $filterDue = '';

    protected $queryString = [
        'search' => ['except' => ''], 'filterInstitute' => ['except' => '', 'as' => 'institute'], 'filterCourse' => ['except' => '', 'as' => 'course'],
        'filterStatus' => ['except' => '', 'as' => 'status'], 'filterDue' => ['except' => '', 'as' => 'due'],
    ];

    // form (modal): target institute course + fee line
    public $instituteId = null;
    public $courseId = null;
    public $editingId = null, $fee_head, $amount, $due_days = 0, $sort_order = 0, $status = 1;
    public $due_type = CourseFee::DUE_JOINING; // joining (N days after joining) | fixed (calendar date)
    public $due_date = null;

    public function mount()
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);
        $this->instituteId = Auth::user()->isSuperAdmin() ? null : Auth::user()->institute_id;
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);
        if (!Auth::user()->isSuperAdmin()) {
            $this->instituteId = Auth::user()->institute_id;
            $this->filterInstitute = '';
        }
    }

    public function updated($property)
    {
        if (in_array($property, ['search', 'filterInstitute', 'filterCourse', 'filterStatus', 'filterDue'], true)) {
            $this->resetPage();
        }
        if ($property === 'filterInstitute') {
            $this->filterCourse = '';
        }
    }

    public function updatedInstituteId()
    {
        $this->courseId = null;
        $this->sort_order = $this->nextSortOrder();
    }

    public function updatedCourseId()
    {
        if (!$this->editingId) {
            $this->sort_order = $this->nextSortOrder();
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'filterInstitute', 'filterCourse', 'filterStatus', 'filterDue']);
        $this->resetPage();
    }

    protected function rules()
    {
        return [
            'instituteId' => ['required', Rule::exists('institutes', 'id')],
            'courseId' => ['required', function ($attribute, $value, $fail) {
                if (!InstituteCourse::where('institute_id', $this->instituteId)->where('course_id', $value)->exists()) {
                    $fail('This course is not offered by the selected institute.');
                }
            }],
            'fee_head' => ['required', 'string', 'max:150', Rule::unique('course_fees', 'fee_head')
                ->where('institute_id', $this->instituteId)->where('course_id', $this->courseId)->whereNull('deleted_at')->ignore($this->editingId)],
            'amount' => 'required|numeric|min:1|max:9999999',
            'due_type' => ['required', Rule::in([CourseFee::DUE_JOINING, CourseFee::DUE_FIXED])],
            'due_days' => [$this->due_type === CourseFee::DUE_JOINING ? 'required' : 'nullable', 'integer', 'min:0', 'max:3650'],
            'due_date' => [$this->due_type === CourseFee::DUE_FIXED ? 'required' : 'nullable', 'date', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
            'sort_order' => 'required|integer|min:0|max:999',
            'status' => 'boolean',
        ];
    }

    protected $validationAttributes = ['instituteId' => 'institute', 'courseId' => 'course', 'fee_head' => 'fee', 'due_date' => 'due date', 'due_days' => 'days after joining'];

    /**
     * Open the add modal, optionally for a given course (and institute, for Super Admin).
     */
    public function create($courseId = null, $instituteId = null)
    {
        $this->resetForm();
        if ($instituteId && Auth::user()->isSuperAdmin()) {
            $this->instituteId = (int) $instituteId;
        }
        if ($courseId) {
            $this->courseId = (int) $courseId;
        }
        $this->sort_order = $this->nextSortOrder();
        $this->dispatchBrowserEvent('open-fee-structure-modal');
    }

    public function edit($id)
    {
        $fee = CourseFee::findOrFail($id); // institute-scoped
        $this->resetValidation();
        $this->fill([
            'editingId' => $fee->id, 'instituteId' => $fee->institute_id, 'courseId' => $fee->course_id, 'fee_head' => $fee->fee_head,
            'amount' => $fee->amount, 'due_days' => $fee->due_days, 'sort_order' => $fee->sort_order, 'status' => $fee->status ? 1 : 0,
            'due_type' => $fee->due_type ?: CourseFee::DUE_JOINING, 'due_date' => optional($fee->due_date)->toDateString(),
        ]);
        $this->dispatchBrowserEvent('open-fee-structure-modal');
    }

    public function save()
    {
        $data = $this->validate();
        $fixed = $data['due_type'] === CourseFee::DUE_FIXED;
        $line = [
            'fee_head' => $data['fee_head'], 'amount' => $data['amount'], 'due_type' => $data['due_type'],
            'due_date' => $fixed ? $data['due_date'] : null, 'due_days' => $fixed ? 0 : (int) $data['due_days'],
            'sort_order' => $data['sort_order'], 'status' => (bool) $data['status'],
        ];

        $wasActive = false;
        if ($this->editingId) {
            $fee = CourseFee::findOrFail($this->editingId);
            $wasActive = (bool) $fee->status;
            $old = $fee->only(array_keys($line));
            $fee->update($line);
            $this->auditUpdate($fee, 'course_fees', $old, $fee->only(array_keys($line)), "Updated fee structure: {$fee->fee_head}");
        } else {
            $fee = CourseFee::create($line + ['institute_id' => $this->instituteId, 'course_id' => $this->courseId]);
            $this->auditCreate($fee, 'course_fees', "Added fee structure line: {$fee->fee_head}");
        }

        // New (or re-activated) fee → charge it to the course's current students straight away.
        // Amount / date changes are not pushed to dues already created.
        $charged = (!$this->editingId || !$wasActive) ? app(FeeService::class)->applyFeeToStudents($fee) : 0;

        $this->dispatchBrowserEvent('close-fee-structure-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => 'Fee structure saved.'
            . ($charged ? " {$fee->fee_head} added to {$charged} " . Str::plural('student', $charged) . '.' : '')
            . ($this->editingId ? ' Changes apply to students charged from now on; existing dues stay as they are.' : '')]);
        $this->resetForm();
    }

    public function toggleStatus($id)
    {
        $fee = CourseFee::findOrFail($id);
        $old = ['status' => $fee->status];
        $fee->update(['status' => !$fee->status]);
        $this->auditUpdate($fee, 'course_fees', $old, ['status' => $fee->status], ($fee->status ? 'Activated' : 'Deactivated') . " fee: {$fee->fee_head}");
        $charged = $fee->status ? app(FeeService::class)->applyFeeToStudents($fee) : 0;
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => "{$fee->fee_head} is now " . ($fee->status ? 'active' : 'inactive') . '.'
            . ($charged ? " Added to {$charged} " . Str::plural('student', $charged) . '.' : '')]);
    }

    public function delete($id)
    {
        $fee = CourseFee::findOrFail($id);

        if ($fee->dues()->exists()) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'warning', 'message' => 'This fee is already charged to students. Set it Inactive instead.']);

            return;
        }

        $this->auditDelete($fee, 'course_fees', "Removed fee structure line: {$fee->fee_head}");
        $fee->delete();
        $this->dispatchBrowserEvent('show-toast', ['type' => 'danger', 'message' => 'Fee removed from the structure.']);
    }

    protected function resetForm()
    {
        $this->resetValidation();
        $this->reset(['editingId', 'fee_head', 'amount', 'due_date']);
        $this->due_type = CourseFee::DUE_JOINING;
        if (Auth::user()->isSuperAdmin() && !$this->instituteId && $this->filterInstitute) {
            $this->instituteId = (int) $this->filterInstitute;
        }
        $this->due_days = 0;
        $this->sort_order = $this->nextSortOrder();
        $this->status = 1;
    }

    protected function nextSortOrder(): int
    {
        if (!$this->instituteId || !$this->courseId) {
            return 1;
        }

        return (int) CourseFee::where('institute_id', $this->instituteId)->where('course_id', $this->courseId)->max('sort_order') + 1;
    }

    protected function feeQuery()
    {
        $isSuperAdmin = Auth::user()->isSuperAdmin();

        $query = CourseFee::query()
            ->when($isSuperAdmin && $this->filterInstitute, fn ($q) => $q->where('course_fees.institute_id', $this->filterInstitute))
            ->when($this->filterCourse, fn ($q) => $q->where('course_fees.course_id', $this->filterCourse))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('course_fees.status', $this->filterStatus === 'active'))
            ->when($this->filterDue === 'joining', fn ($q) => $q->where('course_fees.due_type', CourseFee::DUE_JOINING)->where('course_fees.due_days', 0))
            ->when($this->filterDue === 'later', fn ($q) => $q->where('course_fees.due_type', CourseFee::DUE_JOINING)->where('course_fees.due_days', '>', 0))
            ->when($this->filterDue === 'fixed', fn ($q) => $q->where('course_fees.due_type', CourseFee::DUE_FIXED));

        if ($term = trim($this->search)) {
            $query->where(fn ($q) => $q->where('course_fees.fee_head', 'like', "%{$term}%")
                ->orWhereHas('course', fn ($c) => $c->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")));
        }

        return $query;
    }

    public function render()
    {
        $user = Auth::user();
        $isSuperAdmin = $user->isSuperAdmin();

        // One card per institute course (paged by course, so a course's fees are never split across pages)
        $groups = $this->feeQuery()
            ->join('courses', 'courses.id', '=', 'course_fees.course_id')
            ->join('institutes', 'institutes.id', '=', 'course_fees.institute_id')
            ->selectRaw('course_fees.institute_id, course_fees.course_id, MAX(institutes.name) as institute_name, MAX(courses.code) as course_code')
            ->groupBy('course_fees.institute_id', 'course_fees.course_id')
            ->orderBy('institute_name')->orderBy('course_code')
            ->paginate(self::PER_PAGE);

        // The (filtered) fee lines of the courses on this page
        $pageKeys = $groups->getCollection();
        $feesByGroup = $pageKeys->isEmpty() ? collect() : $this->feeQuery()
            ->with(['course', 'institute'])
            ->withCount('dues')
            ->where(function ($q) use ($pageKeys) {
                foreach ($pageKeys as $key) {
                    $q->orWhere(fn ($w) => $w->where('course_fees.institute_id', $key->institute_id)->where('course_fees.course_id', $key->course_id));
                }
            })
            ->orderBy('course_fees.sort_order')->orderBy('course_fees.id')
            ->get()
            ->groupBy(fn ($fee) => "{$fee->institute_id}-{$fee->course_id}");

        // Per-course summary for the card headers (whole structure, not only the filtered lines)
        $groupTotals = CourseFee::selectRaw("institute_id, course_id, COUNT(*) as line_count,
                SUM(CASE WHEN status = 1 THEN amount ELSE 0 END) as active_total,
                SUM(CASE WHEN status = 1 AND due_type = 'joining' AND due_days = 0 THEN amount ELSE 0 END) as joining_total,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_count,
                MIN(CASE WHEN status = 1 AND due_type = 'fixed' AND due_date >= CURDATE() THEN due_date END) as next_fixed_due")
            ->groupBy('institute_id', 'course_id')->get()
            ->keyBy(fn ($r) => "{$r->institute_id}-{$r->course_id}");

        // Offered courses (respecting the institute filter) and those still without any fee line
        $scopeInstitute = $isSuperAdmin ? $this->filterInstitute : $user->institute_id;
        $offered = InstituteCourse::with(['course', 'institute'])
            ->when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute))
            ->get()->filter(fn ($ic) => $ic->course);
        $missing = $offered->reject(fn ($ic) => $groupTotals->has("{$ic->institute_id}-{$ic->course_id}"))->values();

        $base = fn () => CourseFee::when($isSuperAdmin && $this->filterInstitute, fn ($q) => $q->where('institute_id', $this->filterInstitute));
        $configured = $offered->count() - $missing->count();
        $activeTotals = $groupTotals->filter(fn ($r, $key) => $offered->contains(fn ($ic) => "{$ic->institute_id}-{$ic->course_id}" === $key))->pluck('active_total');

        // Courses for the filter and the modal: those offered by the chosen institute
        $filterCourseIds = InstituteCourse::when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute))->pluck('course_id');
        $modalCourses = $this->instituteId
            ? InstituteCourse::with('course')->where('institute_id', $this->instituteId)->get()->pluck('course')->filter()->sortBy('code')
            : collect();

        return view('livewire.admin.onboarding.fee-structure-component', [
            'groups' => $groups,
            'feesByGroup' => $feesByGroup,
            'lineCount' => $this->feeQuery()->count(),
            // open the cards straight away when the list is narrowed down (one course, a search, or few courses)
            'autoOpen' => $this->filterCourse || trim($this->search) !== '' || $groups->total() <= 2,
            'groupTotals' => $groupTotals,
            'missing' => $missing,
            'stats' => [
                'lines' => $base()->count(),
                'active' => $base()->where('status', true)->count(),
                'inactive' => $base()->where('status', false)->count(),
                'offered' => $offered->count(),
                'configured' => $configured,
                'missing' => $missing->count(),
                'avgTotal' => $activeTotals->count() ? (float) $activeTotals->avg() : 0,
                'maxTotal' => $activeTotals->count() ? (float) $activeTotals->max() : 0,
            ],
            'institutes' => $isSuperAdmin ? Institute::orderBy('name')->get(['id', 'name', 'code']) : Institute::whereKey($user->institute_id)->get(['id', 'name', 'code']),
            'filterCourses' => Course::whereIn('id', $filterCourseIds)->orderBy('code')->get(['id', 'code', 'name']),
            'modalCourses' => $modalCourses,
            'isSuperAdmin' => $isSuperAdmin,
            'hasFilters' => $this->search || $this->filterInstitute || $this->filterCourse || $this->filterStatus !== '' || $this->filterDue,
        ])->layout('layouts.admin.master');
    }
}
