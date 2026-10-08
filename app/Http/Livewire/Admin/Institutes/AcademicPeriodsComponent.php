<?php

namespace App\Http\Livewire\Admin\Institutes;

use App\Models\Admin\AcademicYear;
use App\Models\Admin\Batch;
use App\Models\Admin\Course;
use App\Models\Admin\CoursePeriod;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\StudentPeriod;
use App\Services\PeriodService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Institute Management → Academic Periods (Module 2B): the semesters / terms / modules of each
 * institute course per intake (academic year of period 1), optionally per batch. Generated from the
 * course's period structure; dates and status can be adjusted.
 * Super Admin: every institute; Institute Admin (periods.manage): own institute.
 */
class AcademicPeriodsComponent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    const PER_PAGE = 8; // calendars (cards) per page

    // filters
    public $filterInstitute = '';
    public $filterCourse = '';
    public $filterIntake = '';

    protected $queryString = [
        'filterInstitute' => ['except' => '', 'as' => 'institute'], 'filterCourse' => ['except' => '', 'as' => 'course'],
        'filterIntake' => ['except' => '', 'as' => 'intake'],
    ];

    // generate (modal)
    public $instituteId = null, $courseId = null, $batchId = null, $startDate = null;

    // edit one period (modal)
    public $editingPeriodId = null, $periodStart, $periodEnd, $periodStatus = 'planned';

    public function mount()
    {
        $this->authorizePeriods();
        $this->instituteId = Auth::user()->isSuperAdmin() ? null : Auth::user()->institute_id;
    }

    public function hydrate()
    {
        $this->authorizePeriods();
        if (!Auth::user()->isSuperAdmin()) {
            $this->instituteId = Auth::user()->institute_id;
            $this->filterInstitute = '';
        }
    }

    protected function authorizePeriods()
    {
        abort_unless(Auth::user() && Auth::user()->can('periods.manage'), 403);
    }

    public function updated($property)
    {
        if (in_array($property, ['filterInstitute', 'filterCourse', 'filterIntake'], true)) {
            $this->resetPage();
        }
        if ($property === 'filterInstitute') {
            $this->filterCourse = '';
        }
        if ($property === 'instituteId') {
            $this->courseId = $this->batchId = null;
        }
        if ($property === 'courseId') {
            $this->batchId = null;
        }
        if ($property === 'batchId' && $this->batchId) {
            // a batch's own start date is the start of period 1
            $start = optional(Batch::find($this->batchId))->start_date;
            if ($start) {
                $this->startDate = $start->toDateString();
            }
        }
    }

    public function openGenerate($courseId = null, $instituteId = null)
    {
        $this->resetValidation();
        $this->reset(['batchId']);
        if (Auth::user()->isSuperAdmin()) {
            $this->instituteId = $instituteId ? (int) $instituteId : ($this->filterInstitute ? (int) $this->filterInstitute : $this->instituteId);
        }
        $this->courseId = $courseId ? (int) $courseId : ($this->filterCourse ? (int) $this->filterCourse : null);
        $year = AcademicYear::current() ?? AcademicYear::active()->orderBy('start_date')->first();
        $this->startDate = optional(optional($year)->start_date)->toDateString() ?? now()->toDateString();
        $this->dispatchBrowserEvent('open-periods-modal');
    }

    public function generate()
    {
        if (!Auth::user()->isSuperAdmin()) {
            $this->instituteId = Auth::user()->institute_id;
        }
        $this->validate([
            'instituteId' => ['required', Rule::exists('institutes', 'id')->whereNull('deleted_at')],
            'courseId' => ['required', function ($attribute, $value, $fail) {
                if (!InstituteCourse::where('institute_id', $this->instituteId)->where('course_id', $value)->exists()) {
                    $fail('This course is not offered by the selected institute.');
                }
            }],
            'batchId' => ['nullable', Rule::exists('batches', 'id')->where('institute_id', $this->instituteId)->where('course_id', $this->courseId)->whereNull('deleted_at')],
            'startDate' => ['required', 'date', 'after:2000-01-01'],
        ], [], ['instituteId' => 'institute', 'courseId' => 'course', 'batchId' => 'batch', 'startDate' => 'start date of period 1']);

        try {
            $periods = app(PeriodService::class)->generate(
                Institute::findOrFail($this->instituteId), Course::findOrFail($this->courseId), $this->startDate,
                $this->batchId ? Batch::findOrFail($this->batchId) : null
            );
        } catch (RuntimeException $e) {
            $this->addError('startDate', $e->getMessage());

            return;
        }

        $this->dispatchBrowserEvent('close-periods-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => "{$periods->count()} periods created for " . optional($periods->first()->course)->code . '.']);
    }

    public function editPeriod($id)
    {
        $period = CoursePeriod::findOrFail($id); // institute-scoped
        $this->resetValidation();
        $this->editingPeriodId = $period->id;
        $this->periodStart = $period->start_date->toDateString();
        $this->periodEnd = $period->end_date->toDateString();
        $this->periodStatus = $period->status;
        $this->dispatchBrowserEvent('open-period-edit-modal');
    }

    public function savePeriod()
    {
        $period = CoursePeriod::findOrFail($this->editingPeriodId);
        $this->validate([
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after:periodStart'],
            'periodStatus' => ['required', Rule::in(array_keys(CoursePeriod::STATUSES))],
        ], [], ['periodStart' => 'start date', 'periodEnd' => 'end date', 'periodStatus' => 'status']);

        app(PeriodService::class)->update($period, $this->periodStart, $this->periodEnd, $this->periodStatus);
        $this->dispatchBrowserEvent('close-period-edit-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => "{$period->label} updated."]);
    }

    public function removeCalendar($periodId)
    {
        try {
            $count = app(PeriodService::class)->deleteCalendar(CoursePeriod::findOrFail($periodId));
            $this->dispatchBrowserEvent('show-toast', ['type' => 'danger', 'message' => "{$count} periods removed."]);
        } catch (RuntimeException $e) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'warning', 'message' => $e->getMessage()]);
        }
    }

    /** What "Generate" will create (shown in the modal before saving). */
    protected function preview(): array
    {
        $course = $this->courseId ? Course::find($this->courseId) : null;
        if (!$course || !$this->startDate) {
            return [];
        }
        try {
            $start = Carbon::parse($this->startDate);
        } catch (\Throwable $e) {
            return [];
        }
        $rows = [];
        for ($n = 1; $n <= $course->total_periods; $n++) {
            $from = $start->copy()->addMonthsNoOverflow(($n - 1) * $course->period_length);
            $rows[] = [
                'label' => $course->periodName($n), 'year' => $course->yearOfStudy($n),
                'from' => $from, 'to' => $start->copy()->addMonthsNoOverflow($n * $course->period_length)->subDay(),
                'academic_year' => optional(AcademicYear::forDate($from))->name,
            ];
        }

        return $rows;
    }

    public function render()
    {
        $user = Auth::user();
        $isSuperAdmin = $user->isSuperAdmin();
        $scopeInstitute = $isSuperAdmin ? $this->filterInstitute : $user->institute_id;

        // one card per calendar: institute + course + intake (+ batch)
        $calendars = CoursePeriod::query()
            ->when($scopeInstitute, fn ($q) => $q->where('course_periods.institute_id', $scopeInstitute))
            ->when($this->filterCourse, fn ($q) => $q->where('course_periods.course_id', $this->filterCourse))
            ->when($this->filterIntake, fn ($q) => $q->where('course_periods.intake_academic_year_id', $this->filterIntake))
            ->selectRaw('course_periods.institute_id, course_periods.course_id, course_periods.intake_academic_year_id, course_periods.batch_id, MIN(course_periods.start_date) as first_start')
            ->groupBy('course_periods.institute_id', 'course_periods.course_id', 'course_periods.intake_academic_year_id', 'course_periods.batch_id')
            ->orderByDesc('first_start')
            ->paginate(self::PER_PAGE);

        $periodsByCalendar = collect();
        if ($calendars->isNotEmpty()) {
            $periodsByCalendar = CoursePeriod::with(['course', 'institute', 'batch', 'intakeYear', 'academicYear'])
                ->withCount(['studentPeriods as students_current' => fn ($q) => $q->where('status', 'current')])
                ->where(function ($q) use ($calendars) {
                    foreach ($calendars as $c) {
                        $q->orWhere(fn ($w) => $w->where('institute_id', $c->institute_id)->where('course_id', $c->course_id)
                            ->where('intake_academic_year_id', $c->intake_academic_year_id)
                            ->when($c->batch_id, fn ($b) => $b->where('batch_id', $c->batch_id), fn ($b) => $b->whereNull('batch_id')));
                    }
                })
                ->orderBy('period_no')->get()
                ->groupBy(fn ($p) => "{$p->institute_id}-{$p->course_id}-{$p->intake_academic_year_id}-" . ($p->batch_id ?: 0));
        }

        $courseIds = InstituteCourse::when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute))->pluck('course_id');
        $modalCourse = $this->courseId ? Course::find($this->courseId) : null;

        return view('livewire.admin.institutes.academic-periods-component', [
            'calendars' => $calendars,
            'periodsByCalendar' => $periodsByCalendar,
            'isSuperAdmin' => $isSuperAdmin,
            'institutes' => $isSuperAdmin ? Institute::orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'filterCourses' => Course::whereIn('id', $courseIds)->orderBy('code')->get(),
            'intakes' => AcademicYear::active()->orderByDesc('start_date')->get(),
            'modalCourses' => $this->instituteId
                ? InstituteCourse::with('course')->where('institute_id', $this->instituteId)->get()->pluck('course')->filter()->sortBy('code')
                : collect(),
            'modalBatches' => $this->instituteId && $this->courseId
                ? Batch::where('institute_id', $this->instituteId)->where('course_id', $this->courseId)->orderByDesc('start_date')->get()
                : collect(),
            'modalCourse' => $modalCourse,
            'preview' => $this->preview(),
            'stats' => [
                'calendars' => $calendars->total(),
                'periods' => CoursePeriod::when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute))->count(),
                'ongoing' => CoursePeriod::when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute))
                    ->where('status', '!=', 'completed')->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->count(),
                'students' => StudentPeriod::when($scopeInstitute, fn ($q) => $q->where('institute_id', $scopeInstitute))->where('status', 'current')->count(),
            ],
            'hasFilters' => $this->filterInstitute || $this->filterCourse || $this->filterIntake,
        ])->layout('layouts.admin.master');
    }
}
