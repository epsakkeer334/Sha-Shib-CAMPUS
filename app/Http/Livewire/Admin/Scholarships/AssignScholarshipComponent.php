<?php

namespace App\Http\Livewire\Admin\Scholarships;

use App\Models\Admin\Batch;
use App\Models\Admin\Scholarship;
use App\Models\Admin\StudentScholarship;
use App\Services\ScholarshipService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Scholarships ▸ Assign Scholarship (Module 2C): pick a scholarship, see its eligible students with the
 * semester-wise fee, existing discounts, this scholarship and the final payable, then assign it to one,
 * selected or all eligible students. The "Assigned" tab lists who has it, with Revoke.
 */
class AssignScholarshipComponent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $scholarship = '';
    public $tab = 'eligible'; // eligible | assigned
    public $search = '';
    public $batch = '';
    public $selected = [];
    public $feesFor = null;       // student whose complete fee structure is shown in the pop-up

    // filters of the scholarship cards
    public $cardSearch = '';
    public $cardInstitute = '';
    public $cardCourse = '';
    public $cardType = '';
    public $cardOnlyEligible = false;

    // filters of the eligible students (besides search + batch)
    public $semester = '';
    public $feeState = '';        // '' | open (something still to pay) | settled (nothing to pay)
    public $assignOnly = null;    // one student from a row's Assign button (the bulk selection stays)
    public $remarks = '';
    public $confirm = null;      // ['count' => n, 'total' => ₹]
    public $revokeId = null;
    public $revokeReason = '';

    protected $queryString = ['scholarship' => ['except' => ''], 'tab' => ['except' => 'eligible'], 'search' => ['except' => ''],
        'cardSearch' => ['except' => '', 'as' => 'q'], 'cardCourse' => ['except' => '', 'as' => 'course'], 'cardType' => ['except' => '', 'as' => 'type']];

    public function mount()
    {
        abort_unless(Auth::user()->can('scholarships.assign'), 403);
        if ($this->scholarship && !Scholarship::find($this->scholarship)) {
            $this->scholarship = '';
        }
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('scholarships.assign'), 403);
    }

    public function updatingScholarship()
    {
        $this->reset(['selected', 'batch', 'search', 'semester', 'feeState', 'confirm', 'feesFor']);
        $this->resetPage();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingBatch()
    {
        $this->resetPage();
    }

    public function updatingSemester()
    {
        $this->resetPage();
    }

    public function updatingFeeState()
    {
        $this->resetPage();
    }

    public function updatedCardInstitute()
    {
        $this->cardCourse = '';
    }

    public function clearCardFilters()
    {
        $this->reset(['cardSearch', 'cardInstitute', 'cardCourse', 'cardType', 'cardOnlyEligible']);
    }

    public function clearStudentFilters()
    {
        $this->reset(['search', 'batch', 'semester', 'feeState']);
        $this->resetPage();
    }

    public function setTab($tab)
    {
        $this->tab = $tab === 'assigned' ? 'assigned' : 'eligible';
        $this->resetPage();
    }

    protected function current(): ?Scholarship
    {
        return $this->scholarship ? Scholarship::with(['course', 'batch', 'academicYear', 'periodRows'])->find($this->scholarship) : null;
    }

    /** Eligible students not having it yet (institute isolation through the scholarship's own scope). */
    protected function candidates(Scholarship $s)
    {
        return app(ScholarshipService::class)->eligibleQuery($s)
            ->when($this->batch && !$s->batch_id, fn ($q) => $q->where('batch_id', $this->batch))
            ->when((int) $this->semester > 0, fn ($q) => $q->whereHas('currentPeriod', fn ($p) => $p->where('period_no', (int) $this->semester)))
            ->when($this->feeState === 'open', fn ($q) => $q->whereHas('dues', fn ($d) => $d->whereIn('status', ['pending', 'partial'])))
            ->when($this->feeState === 'settled', fn ($q) => $q->whereDoesntHave('dues', fn ($d) => $d->whereIn('status', ['pending', 'partial'])))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('er_number', 'like', $term)->orWhere('email', 'like', $term));
            });
    }

    protected function assignedIds(Scholarship $s): array
    {
        return StudentScholarship::withoutGlobalScopes()->where('scholarship_id', $s->id)->where('status', 'active')->pluck('student_id')->all();
    }

    public function toggle($id)
    {
        $id = (string) $id;
        $this->selected = in_array($id, $this->selected, true) ? array_values(array_diff($this->selected, [$id])) : array_merge($this->selected, [$id]);
    }

    /** Pick a scholarship from the cards (the student list opens below). */
    public function chooseScholarship($id)
    {
        $this->updatingScholarship();
        $this->scholarship = Scholarship::active()->findOrFail($id)->id;
        $this->tab = 'eligible';
    }

    /** Back to the list of scholarships. */
    public function changeScholarship()
    {
        $this->updatingScholarship();
        $this->scholarship = '';
        $this->tab = 'eligible';
    }

    /** A student's complete fee structure in a pop-up. */
    public function showFees($id)
    {
        $s = $this->current();
        abort_unless($s && $this->candidates($s)->whereKey($id)->exists(), 404);
        $this->feesFor = (int) $id;
        $this->dispatchBrowserEvent('open-fees-modal');
    }

    public function selectPage($ids)
    {
        $s = $this->current();
        if (!$s) {
            return;
        }
        $have = $this->assignedIds($s);
        $ids = array_map('strval', array_diff(array_map('intval', (array) $ids), $have));
        $this->selected = count(array_intersect($ids, $this->selected)) === count($ids)
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique(array_merge($this->selected, $ids)));
    }

    public function selectAllEligible()
    {
        $s = $this->current();
        if ($s) {
            $this->selected = $this->candidates($s)->whereNotIn('id', $this->assignedIds($s))->pluck('id')->map(fn ($id) => (string) $id)->all();
        }
    }

    public function clearSelection()
    {
        $this->selected = [];
    }

    public function confirmAssign($single = null)
    {
        $s = $this->current();
        if (!$s) {
            return;
        }
        $this->assignOnly = $single ? (string) $single : null;
        $ids = $this->assignOnly ? [$this->assignOnly] : $this->selected;
        if (!$ids) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'warning', 'message' => 'Select at least one student.']);

            return;
        }
        $summary = $this->summary($s, $ids);
        $this->confirm = [
            'name' => $this->assignOnly ? ($summary['students'][0]['name'] ?? null) : null,
            'count' => $summary['count'],
            'total' => $summary['this_now'],
            'later' => $summary['this_later'],
            'fees' => $summary['fees'],
            'existing' => $summary['existing'],
            'payable' => $summary['payable_now'],
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
        $this->revokeId = StudentScholarship::findOrFail($id)->id;
        $this->revokeReason = '';
        $this->resetValidation();
        $this->dispatchBrowserEvent('open-revoke-modal');
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
        $this->reset(['revokeId', 'revokeReason']);
        $this->dispatchBrowserEvent('close-revoke-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => 'Scholarship revoked — the discount was removed from unpaid fees.']);
    }

    /**
     * Totals of the selected students (complete fee structures) for the review panel and the confirmation.
     */
    protected function summary(Scholarship $s, ?array $ids = null): array
    {
        $ids = $ids ?? $this->selected;
        $service = app(ScholarshipService::class);
        $out = ['count' => 0, 'fees' => 0.0, 'existing' => 0.0, 'this_now' => 0.0, 'this_later' => 0.0, 'paid' => 0.0, 'payable_now' => 0.0, 'students' => []];
        if (!$ids) {
            return $out;
        }
        $students = app(ScholarshipService::class)->eligibleQuery($s)->whereIn('id', $ids)->whereNotIn('id', $this->assignedIds($s))->with('course')->orderBy('first_name')->get();
        foreach ($students as $st) {
            $t = $service->structure($s, $st)['totals'];
            $out['count']++;
            foreach (['fees', 'existing', 'this_now', 'this_later', 'paid', 'payable_now'] as $k) {
                $out[$k] += $t[$k];
            }
            $out['students'][] = ['id' => $st->id, 'name' => $st->full_name, 'er' => $st->er_number, 'initials' => $st->initials, 'this' => $t['this_now'], 'later' => $t['this_later'], 'payable' => $t['payable_now']];
        }
        foreach (['fees', 'existing', 'this_now', 'this_later', 'paid', 'payable_now'] as $k) {
            $out[$k] = round($out[$k], 2);
        }

        return $out;
    }

    public function render()
    {
        $s = $this->current();
        $service = app(ScholarshipService::class);
        $students = collect();
        $previews = [];
        $structures = [];
        $summary = null;
        $assigned = collect();
        $counts = ['eligible' => 0, 'assigned' => 0];

        if ($s) {
            $have = $this->assignedIds($s);
            $counts['assigned'] = count($have);
            $counts['eligible'] = $this->candidates($s)->whereNotIn('id', $have)->count();
            if ($this->tab === 'eligible') {
                $students = $this->candidates($s)->with(['batch', 'course', 'currentPeriod.coursePeriod.academicYear'])->orderBy('first_name')->paginate(15);
                foreach ($students as $st) {
                    $structures[$st->id] = $service->structure($s, $st);
                }
                $summary = $this->summary($s);
            } else {
                $assigned = StudentScholarship::with(['student.batch', 'assigner', 'revoker'])->withSum('discounts as discount_total', 'amount')
                    ->where('scholarship_id', $s->id)->orderByRaw("status = 'active' desc")->latest('assigned_at')->paginate(15);
            }
        }

        return view('livewire.admin.scholarships.assign-scholarship-component', [
            's' => $s,
            'scholarships' => $all = Scholarship::with(['course', 'batch', 'periodRows', 'institute'])->active()->orderBy('name')->get(),
            'cardCounts' => $cardCounts = $s ? [] : $all->mapWithKeys(fn ($sc) => [$sc->id => [
                'assigned' => $assignedCount = StudentScholarship::withoutGlobalScopes()->where('scholarship_id', $sc->id)->where('status', 'active')->count(),
                'eligible' => max(0, $service->eligibleQuery($sc)->count() - $assignedCount),
            ]])->all(),
            'cards' => $s ? collect() : $all
                ->when(trim($this->cardSearch) !== '', fn ($c) => $c->filter(fn ($sc) => str_contains(mb_strtolower($sc->name . ' ' . $sc->code . ' ' . optional($sc->course)->name . ' ' . optional($sc->course)->code), mb_strtolower(trim($this->cardSearch)))))
                ->when($this->cardInstitute, fn ($c) => $c->where('institute_id', (int) $this->cardInstitute))
                ->when($this->cardCourse, fn ($c) => $c->where('course_id', (int) $this->cardCourse))
                ->when($this->cardType, fn ($c) => $c->where('type', $this->cardType))
                ->when($this->cardOnlyEligible, fn ($c) => $c->filter(fn ($sc) => ($cardCounts[$sc->id]['eligible'] ?? 0) > 0))
                ->values(),
            'cardInstitutes' => Auth::user()->isSuperAdmin() ? $all->pluck('institute')->filter()->unique('id')->sortBy('name')->values() : collect(),
            'cardCourses' => $all->when($this->cardInstitute, fn ($c) => $c->where('institute_id', (int) $this->cardInstitute))->pluck('course')->filter()->unique('id')->sortBy('name')->values(),
            'semesterCount' => $s ? max(1, (int) optional($s->course)->total_periods) : 0,
            'feeStudent' => $feeStudent = ($s && $this->feesFor ? $this->candidates($s)->with(['batch', 'course', 'currentPeriod.coursePeriod.academicYear'])->find($this->feesFor) : null),
            'feeStructure' => $feeStudent ? ($structures[$feeStudent->id] ?? $service->structure($s, $feeStudent)) : null,
            'students' => $students,
            'previews' => $previews,
            'structures' => $structures,
            'summary' => $summary,
            'have' => $s ? $this->assignedIds($s) : [],
            'assigned' => $assigned,
            'counts' => $counts,
            'batches' => $s && !$s->batch_id ? Batch::where('course_id', $s->course_id)->where('institute_id', $s->institute_id)->orderBy('code')->get(['id', 'code']) : collect(),
        ])->layout('layouts.admin.master');
    }
}
