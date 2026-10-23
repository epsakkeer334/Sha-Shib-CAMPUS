<?php

namespace App\Http\Livewire\Admin\Scholarships;

use App\Models\Admin\Batch;
use App\Models\Admin\Course;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\Scholarship;
use App\Models\Admin\Student;
use App\Models\Admin\StudentScholarship;
use App\Services\ScholarshipService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Scholarships ▸ Assign Scholarship (Module 2C) — student first.
 *
 * Lists the admitted students (ER number) with their fees: total fees, existing discounts and what they owe
 * now. Filters: search, institute (Super Admin), course, batch, current semester and scholarship. With a
 * scholarship chosen, the list shows its Eligible / Assigned / Revoked students with the scholarship amount
 * and the payable after assigning; students can be selected one by one or in bulk and assigned through the
 * review panel. Every student's complete fee structure opens in a pop-up.
 */
class AssignScholarshipComponent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    const ADMITTED = ['er_issued', 'active'];

    // filters
    public $search = '';
    public $institute = '';
    public $course = '';
    public $batch = '';
    public $semester = '';
    public $scholarship = '';
    public $show = 'all';         // without scholarship: all | with | without — with: eligible | assigned | revoked

    // selection, pop-ups
    public $selected = [];
    public $assignOnly = null;    // one student from a row's Assign button (the bulk selection stays)
    public $feesFor = null;
    public $remarks = '';
    public $confirm = null;
    public $revokeId = null;
    public $revokeReason = '';
    public $revokeInfo = null;

    protected $queryString = [
        'search' => ['except' => ''], 'institute' => ['except' => ''], 'course' => ['except' => ''], 'batch' => ['except' => ''],
        'semester' => ['except' => ''], 'scholarship' => ['except' => ''], 'show' => ['except' => 'all'],
    ];

    public function mount()
    {
        abort_unless(Auth::user()->can('scholarships.assign'), 403);
        if (!Auth::user()->isSuperAdmin()) {
            $this->institute = '';
        }
        // links from the Scholarships page: ?scholarship=ID[&tab=assigned]
        $tab = request()->query('tab');
        if ($this->scholarship && ($s = Scholarship::find($this->scholarship))) {
            $this->applyScholarship($s);
            $this->show = in_array($tab, ['assigned', 'revoked'], true) ? $tab : ($this->show !== 'all' ? $this->show : 'eligible');
        } else {
            $this->scholarship = '';
            $this->show = in_array($this->show, ['all', 'with', 'without'], true) ? $this->show : 'all';
        }
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('scholarships.assign'), 403);
    }

    // ------------------------------------------------------------------ filters

    public function updating($property)
    {
        if (in_array($property, ['search', 'institute', 'course', 'batch', 'semester', 'show'], true)) {
            $this->resetPage();
        }
    }

    public function updatedInstitute()
    {
        $this->course = $this->batch = '';
        $this->dropScholarshipIfOutside();
    }

    public function updatedCourse()
    {
        $this->batch = '';
        $this->dropScholarshipIfOutside();
    }

    public function updatedScholarship($value)
    {
        $this->selected = [];
        $this->feesFor = null;
        $this->resetPage();
        $s = $value ? Scholarship::active()->find($value) : null;
        if ($s) {
            $this->applyScholarship($s);
            $this->show = 'eligible';
        } else {
            $this->scholarship = '';
            $this->show = 'all';
        }
    }

    /** A scholarship belongs to one institute + course (+ batch): the filters follow it. */
    protected function applyScholarship(Scholarship $s): void
    {
        $this->scholarship = (string) $s->id;
        if (Auth::user()->isSuperAdmin()) {
            $this->institute = (string) $s->institute_id;
        }
        $this->course = (string) $s->course_id;
        if ($s->batch_id) {
            $this->batch = (string) $s->batch_id;
        }
    }

    protected function dropScholarshipIfOutside(): void
    {
        $s = $this->current();
        if ($s && (($this->course && (int) $this->course !== (int) $s->course_id) || ($this->institute && (int) $this->institute !== (int) $s->institute_id))) {
            $this->scholarship = '';
            $this->show = 'all';
            $this->selected = [];
        }
    }

    /** Kept for links / older calls: choose a scholarship. */
    public function chooseScholarship($id)
    {
        $this->updatedScholarship((string) $id);
    }

    /** Custom dropdowns set the filters by click (same hooks as typing into the fields). */
    public function pickScholarship($id)
    {
        $this->scholarship = $id ? (string) (int) $id : '';
        $this->updatedScholarship($this->scholarship);
    }

    public function pickInstitute($id)
    {
        $this->institute = $id ? (string) (int) $id : '';
        $this->resetPage();
        $this->updatedInstitute();
    }

    public function pickCourse($id)
    {
        $this->course = $id ? (string) (int) $id : '';
        $this->resetPage();
        $this->updatedCourse();
    }

    public function pickBatch($id)
    {
        $this->batch = $id ? (string) (int) $id : '';
        $this->resetPage();
    }

    public function pickSemester($n)
    {
        $this->semester = (int) $n > 0 ? (string) (int) $n : '';
        $this->resetPage();
    }

    public function setShow($show)
    {
        $allowed = $this->scholarship ? ['eligible', 'assigned', 'revoked'] : ['all', 'with', 'without'];
        $this->show = in_array($show, $allowed, true) ? $show : $allowed[0];
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'batch', 'semester', 'selected', 'feesFor']);
        if (!$this->scholarship) {
            $this->reset(['institute', 'course']);
        }
        $this->resetPage();
    }

    protected function current(): ?Scholarship
    {
        return $this->scholarship ? Scholarship::with(['course', 'batch', 'academicYear', 'periodRows'])->find($this->scholarship) : null;
    }

    // ------------------------------------------------------------------ queries

    /** Admitted students matching the filters (institute users: own institute through the global scope). */
    protected function baseQuery(bool $byBatch = true, bool $bySemester = true)
    {
        return Student::query()->whereNotNull('er_number')->whereIn('status', self::ADMITTED)
            ->when(Auth::user()->isSuperAdmin() && $this->institute, fn ($q) => $q->where('institute_id', $this->institute))
            ->when($this->course, fn ($q) => $q->where('course_id', $this->course))
            ->when($byBatch && $this->batch, fn ($q) => $q->where('batch_id', $this->batch))
            ->when($bySemester && (int) $this->semester > 0, fn ($q) => $q->whereHas('currentPeriod', fn ($p) => $p->where('period_no', (int) $this->semester)))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)
                    ->orWhere('er_number', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            });
    }

    /** The list for the current tab. */
    protected function listQuery(?Scholarship $s, ?string $show = null)
    {
        $show = $show ?? $this->show;
        $q = $this->baseQuery();
        if (!$s) {
            return $q->when($show === 'with', fn ($w) => $w->whereHas('scholarships', fn ($a) => $a->where('status', 'active')))
                ->when($show === 'without', fn ($w) => $w->whereDoesntHave('scholarships', fn ($a) => $a->where('status', 'active')));
        }
        $has = fn ($status) => fn ($a) => $a->where('scholarship_id', $s->id)->where('status', $status);

        return match ($show) {
            'assigned' => $q->whereHas('scholarships', $has('active')),
            'revoked' => $q->whereHas('scholarships', $has('revoked'))->whereDoesntHave('scholarships', $has('active')),
            default => $q->whereIn('id', app(ScholarshipService::class)->eligibleQuery($s)->select('id'))->whereDoesntHave('scholarships', $has('active')),
        };
    }

    // ------------------------------------------------------------------ selection

    public function toggle($id)
    {
        $id = (string) $id;
        $this->selected = in_array($id, $this->selected, true) ? array_values(array_diff($this->selected, [$id])) : array_merge($this->selected, [$id]);
    }

    public function selectPage($ids)
    {
        if (!$this->current()) {
            return;
        }
        $ids = array_map('strval', (array) $ids);
        $this->selected = !array_diff($ids, $this->selected)
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique(array_merge($this->selected, $ids)));
    }

    public function selectAllEligible()
    {
        $s = $this->current();
        if ($s) {
            $this->selected = $this->listQuery($s, 'eligible')->pluck('id')->map(fn ($id) => (string) $id)->all();
        }
    }

    public function clearSelection()
    {
        $this->selected = [];
    }

    public function showFees($id)
    {
        abort_unless($this->baseQuery()->whereKey($id)->exists() || Student::whereKey($id)->exists(), 404);
        $this->feesFor = (int) $id;
        $this->dispatchBrowserEvent('open-fees-modal');
    }

    // ------------------------------------------------------------------ assign / revoke

    public function confirmAssign($single = null)
    {
        $s = $this->current();
        if (!$s) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'warning', 'message' => 'Choose the scholarship to assign first.']);

            return;
        }
        $this->assignOnly = $single ? (string) $single : null;
        $ids = $this->assignOnly ? [$this->assignOnly] : $this->selected;
        if (!$ids) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'warning', 'message' => 'Select at least one student.']);

            return;
        }
        $summary = $this->summary($s, $ids);
        if (!$summary['count']) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'warning', 'message' => 'None of the selected students can get this scholarship.']);

            return;
        }
        $this->confirm = [
            'name' => $this->assignOnly ? ($summary['students'][0]['name'] ?? null) : null,
            'count' => $summary['count'], 'total' => $summary['this_now'], 'later' => $summary['this_later'],
            'fees' => $summary['fees'], 'existing' => $summary['existing'], 'payable' => $summary['payable_now'],
        ];
        $this->dispatchBrowserEvent('open-assign-modal');
    }

    public function assign()
    {
        $s = $this->current();
        abort_unless($s, 404);
        $this->validate(['remarks' => 'nullable|string|max:300']);

        $ids = $this->assignOnly ? [$this->assignOnly] : $this->selected;
        $result = app(ScholarshipService::class)->assign($s, $ids, trim($this->remarks) ?: null);
        $this->selected = array_values(array_diff($this->selected, array_map('strval', $ids)));
        $this->reset(['remarks', 'confirm', 'assignOnly']);
        $this->dispatchBrowserEvent('close-assign-modal');
        $this->dispatchBrowserEvent('show-toast', [
            'type' => $result['skipped'] ? 'warning' : 'success',
            'message' => "{$s->name} given to {$result['assigned']} " . ($result['assigned'] === 1 ? 'student' : 'students') . ' — ' . money_inr($result['discount']) . ' off their current fees.'
                . ($result['skipped'] ? ' ' . count($result['skipped']) . ' skipped (' . implode('; ', array_unique($result['skipped'])) . ')' : ''),
        ]);
    }

    public function openRevoke($id)
    {
        $assignment = StudentScholarship::with(['student', 'scholarship', 'discounts.due'])->findOrFail($id);
        $this->revokeId = $assignment->id;
        $this->revokeReason = '';
        $paid = fn ($due) => $due && ((float) $due->amount_paid > 0 || $due->payments()->whereIn('status', ['pending_verification', 'success'])->exists());
        $this->revokeInfo = [
            'name' => optional($assignment->student)->full_name,
            'scholarship' => optional($assignment->scholarship)->name,
            'removed' => round((float) $assignment->discounts->reject(fn ($d) => $paid($d->due))->sum('amount'), 2),
            'kept' => round((float) $assignment->discounts->filter(fn ($d) => $paid($d->due))->sum('amount'), 2),
        ];
        $this->resetValidation();
        $this->dispatchBrowserEvent('open-revoke-modal');
    }

    /** Revoke the chosen scholarship of a student (list / fee pop-up). */
    public function openRevokeFor($studentId)
    {
        $s = $this->current();
        abort_unless($s, 404);
        $this->openRevoke(StudentScholarship::where('scholarship_id', $s->id)->where('student_id', $studentId)->where('status', 'active')->firstOrFail()->id);
    }

    public function revoke()
    {
        $this->validate(['revokeReason' => 'required|string|min:5|max:300'], [], ['revokeReason' => 'reason']);
        try {
            app(ScholarshipService::class)->revoke(StudentScholarship::findOrFail($this->revokeId), trim($this->revokeReason));
        } catch (RuntimeException $e) {
            $this->addError('revokeReason', $e->getMessage());

            return;
        }
        $this->reset(['revokeId', 'revokeReason', 'revokeInfo']);
        $this->dispatchBrowserEvent('close-revoke-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => 'Scholarship revoked — the discount was removed from unpaid fees.']);
    }

    /** Totals of the selected students for the review panel and the confirmation. */
    protected function summary(Scholarship $s, ?array $ids = null): array
    {
        $ids = $ids ?? $this->selected;
        $service = app(ScholarshipService::class);
        $out = ['count' => 0, 'fees' => 0.0, 'existing' => 0.0, 'this_now' => 0.0, 'this_later' => 0.0, 'paid' => 0.0, 'payable_now' => 0.0, 'balance_now' => 0.0, 'students' => []];
        if (!$ids) {
            return $out;
        }
        $have = StudentScholarship::withoutGlobalScopes()->where('scholarship_id', $s->id)->where('status', 'active')->pluck('student_id');
        $students = $service->eligibleQuery($s)->whereIn('id', $ids)->whereNotIn('id', $have)->with('course')->orderBy('first_name')->get();
        foreach ($students as $st) {
            $t = $service->structure($s, $st)['totals'];
            $out['count']++;
            foreach (['fees', 'existing', 'this_now', 'this_later', 'paid', 'payable_now', 'balance_now'] as $k) {
                $out[$k] += $t[$k];
            }
            $out['students'][] = ['id' => $st->id, 'name' => $st->full_name, 'er' => $st->er_number, 'initials' => $st->initials,
                'this' => $t['this_now'], 'later' => $t['this_later'], 'payable' => $t['payable_now']];
        }
        foreach (['fees', 'existing', 'this_now', 'this_later', 'paid', 'payable_now', 'balance_now'] as $k) {
            $out[$k] = round($out[$k], 2);
        }

        return $out;
    }

    // ------------------------------------------------------------------ render

    public function render()
    {
        $user = Auth::user();
        $isSuper = $user->isSuperAdmin();
        $s = $this->current();
        $service = app(ScholarshipService::class);

        $students = $this->listQuery($s)
            ->with(['batch', 'course', 'institute', 'currentPeriod.coursePeriod.academicYear', 'scholarships' => fn ($q) => $q->where('status', 'active')->with('scholarship')])
            ->orderBy('first_name')->orderBy('last_name')->paginate(15);
        $structures = [];
        foreach ($students as $st) {
            $structures[$st->id] = $service->structure($s, $st);
        }

        $tabs = $s
            ? ['eligible' => ['Eligible', 'ti ti-user-check', $this->listQuery($s, 'eligible')->count()],
                'assigned' => ['Assigned', 'ti ti-award', $this->listQuery($s, 'assigned')->count()],
                'revoked' => ['Revoked', 'ti ti-award-off', $this->listQuery($s, 'revoked')->count()]]
            : ['all' => ['All students', 'ti ti-users', $this->listQuery(null, 'all')->count()],
                'with' => ['With a scholarship', 'ti ti-award', $this->listQuery(null, 'with')->count()],
                'without' => ['Without', 'ti ti-user', $this->listQuery(null, 'without')->count()]];

        $revoked = [];
        if ($s && $this->show === 'revoked') {
            foreach (StudentScholarship::with(['revoker', 'assigner'])->withSum('discounts as kept', 'amount')->where('scholarship_id', $s->id)
                ->where('status', 'revoked')->whereIn('student_id', $students->pluck('id'))->get() as $a) {
                $revoked[$a->student_id] = $a;
            }
        }

        // filter options
        $instituteId = $isSuper ? $this->institute : $user->institute_id;
        $courseIds = InstituteCourse::when($instituteId, fn ($q) => $q->where('institute_id', $instituteId))->pluck('course_id')->unique();
        $courseModel = $this->course ? Course::find($this->course) : null;

        $feeStudent = $this->feesFor ? Student::with(['batch', 'course', 'currentPeriod.coursePeriod.academicYear'])->find($this->feesFor) : null;

        return view('livewire.admin.scholarships.assign-scholarship-component', [
            's' => $s,
            'isSuperAdmin' => $isSuper,
            'students' => $students,
            'structures' => $structures,
            'tabs' => $tabs,
            'revoked' => $revoked,
            'eligibleIds' => $s ? $this->listQuery($s, 'eligible')->whereIn('id', $students->pluck('id'))->pluck('id')->all() : [],
            'summary' => $s ? $this->summary($s) : null,
            'institutes' => $isSuper ? Institute::orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'courses' => Course::whereIn('id', $courseIds)->orderBy('name')->get(['id', 'name', 'code', 'total_semesters', 'period_label']),
            'batches' => $batchList = ($this->course ? Batch::where('course_id', $this->course)->when($instituteId, fn ($q) => $q->where('institute_id', $instituteId))->orderBy('start_date')->orderBy('code')->get(['id', 'code', 'name', 'start_date', 'status']) : collect()),
            // admitted students per batch / per current semester, with the other filters applied
            'batchCounts' => $batchList->isEmpty() ? [] : $this->baseQuery(false, true)->whereIn('batch_id', $batchList->pluck('id'))
                ->selectRaw('batch_id, COUNT(*) as n')->groupBy('batch_id')->pluck('n', 'batch_id')->all(),
            'batchTotal' => $this->course ? $this->baseQuery(false, true)->count() : 0,
            'semesterCounts' => \App\Models\Admin\StudentPeriod::where('status', 'current')
                ->whereIn('student_id', $this->baseQuery(true, false)->select('students.id'))
                ->selectRaw('period_no, COUNT(*) as n')->groupBy('period_no')->pluck('n', 'period_no')->all(),
            'semesterTotal' => $this->baseQuery(true, false)->count(),
            'semesterCount' => $courseModel ? max(1, (int) $courseModel->total_periods) : max(1, (int) Course::whereIn('id', $courseIds)->max('total_semesters')),
            'periodWord' => optional($courseModel)->period_label ?: 'Semester',
            'scholarships' => Scholarship::with('course')->active()
                ->when($isSuper && $this->institute, fn ($q) => $q->where('institute_id', $this->institute))
                ->orderBy('name')->get(),
            'feeStudent' => $feeStudent,
            'feeStructure' => $feeStudent ? ($structures[$feeStudent->id] ?? $service->structure($s, $feeStudent)) : null,
            'feeHas' => $feeStudent && $s ? StudentScholarship::where('scholarship_id', $s->id)->where('student_id', $feeStudent->id)->where('status', 'active')->exists() : false,
            'feeEligible' => $feeStudent && $s ? $service->problem($s, $feeStudent) === null : false,
        ])->layout('layouts.admin.master');
    }
}
