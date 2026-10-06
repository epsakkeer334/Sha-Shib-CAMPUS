<?php

namespace App\Http\Livewire\Admin\Students;

use App\Models\Admin\Course;
use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\Student;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Students list (Module 2): summary cards, filter bar (institute, course, status, approvals, payment)
 * and a compact table. Institute users only ever see their institute (BelongsToInstitute scope).
 * Only draft students can be deleted.
 */
class StudentsComponent extends Component
{
    use WithPagination, RecordsAuditTrail;

    protected $paginationTheme = 'bootstrap';

    // Filters (kept in the URL)
    public $search = '';
    public $institute = '';
    public $course = '';
    public $status = '';
    public $docsGate = '';
    public $feesGate = '';
    public $payment = '';
    public $overdue = false; // onboarding deadline passed without an ER number
    public $perPage = 15;
    public $sortField = 'id';
    public $sortDirection = 'desc';

    public $confirmingDeleteId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'institute' => ['except' => ''],
        'course' => ['except' => ''],
        'status' => ['except' => ''],
        'docsGate' => ['except' => '', 'as' => 'docs'],
        'feesGate' => ['except' => '', 'as' => 'fees'],
        'payment' => ['except' => ''],
        'overdue' => ['except' => false],
        'sortField' => ['except' => 'id', 'as' => 'sort'],
        'sortDirection' => ['except' => 'desc', 'as' => 'dir'],
    ];

    const SORTABLE = ['id', 'first_name', 'er_number', 'joining_date', 'onboarding_deadline'];

    const GATE_FILTERS = ['not_submitted' => 'Not submitted', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    const PAYMENT_FILTERS = ['paid' => 'Paid', 'part' => 'Part paid', 'unpaid' => 'Unpaid', 'verify' => 'Payment to verify', 'none' => 'No fees'];

    public function mount()
    {
        abort_unless(Auth::user()->can('students.view'), 403);
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('students.view'), 403);
    }

    public function updating($property)
    {
        if (in_array($property, ['search', 'institute', 'course', 'status', 'docsGate', 'feesGate', 'payment', 'overdue', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function updatedInstitute()
    {
        $this->course = '';
    }

    public function sortBy($field)
    {
        if (!in_array($field, self::SORTABLE, true)) {
            return;
        }

        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
    }

    public function clearFilter($filter)
    {
        if ($filter === 'overdue') {
            $this->overdue = false;
            $this->resetPage();

            return;
        }
        if (in_array($filter, ['search', 'institute', 'course', 'status', 'docsGate', 'feesGate', 'payment'], true)) {
            $this->{$filter} = '';
            if ($filter === 'institute') {
                $this->course = '';
            }
            $this->resetPage();
        }
    }

    public function clearAll()
    {
        $this->reset(['search', 'institute', 'course', 'status', 'docsGate', 'feesGate', 'payment', 'overdue']);
        $this->resetPage();
    }

    /**
     * Summary card → filter (one at a time).
     */
    /** Header pill: students whose onboarding deadline passed without an ER number, most overdue first. */
    public function showOverdue()
    {
        $this->reset(['status', 'docsGate', 'feesGate', 'payment']);
        $this->overdue = true;
        $this->sortField = 'onboarding_deadline';
        $this->sortDirection = 'asc';
        $this->resetPage();
    }

    public function quickFilter($key)
    {
        $this->reset(['status', 'docsGate', 'feesGate', 'payment', 'overdue']);
        match ($key) {
            'pending_approval' => $this->status = 'pending_approval',
            'er_issued' => $this->status = 'er_issued',
            'unpaid' => $this->payment = 'unpaid',
            default => null,
        };
        $this->resetPage();
    }

    // ------------------------------------------------------------------ query

    protected function query()
    {
        $user = Auth::user();
        $query = Student::query();

        if ($user->isSuperAdmin() && $this->institute) {
            $query->where('institute_id', $this->institute);
        }
        if ($this->course) {
            $query->where('course_id', $this->course);
        }
        if ($this->status && isset(config('camp.student_statuses')[$this->status])) {
            $query->where('status', $this->status);
        }

        $this->applyGateFilter($query, EnrollmentApproval::DOCUMENTS, $this->docsGate);
        $this->applyGateFilter($query, EnrollmentApproval::FEES, $this->feesGate);
        $this->applyPaymentFilter($query, $this->payment);

        if ($this->overdue) {
            $query->whereNull('er_number')->where('status', '!=', 'rejected')->whereDate('onboarding_deadline', '<', today());
        }

        if ($term = trim($this->search)) {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('er_number', 'like', "%{$term}%")
                ->orWhereHas('course', fn ($c) => $c->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")));
        }

        return $query;
    }

    protected function applyGateFilter($query, string $gate, $value): void
    {
        if (!isset(self::GATE_FILTERS[$value])) {
            return;
        }

        if ($value === 'not_submitted') {
            $query->whereDoesntHave('approvals', fn ($q) => $q->where('gate', $gate));
        } else {
            $query->whereHas('approvals', fn ($q) => $q->where('gate', $gate)->where('status', $value));
        }
    }

    protected function applyPaymentFilter($query, $value): void
    {
        $open = fn ($q) => $q->whereIn('status', ['pending', 'partial']);

        match ($value) {
            'paid' => $query->whereHas('dues')->whereDoesntHave('dues', $open),
            'part' => $query->whereHas('dues', $open)->whereHas('dues', fn ($q) => $q->where('amount_paid', '>', 0)),
            'unpaid' => $query->whereHas('dues', $open)->whereDoesntHave('dues', fn ($q) => $q->where('amount_paid', '>', 0)),
            'verify' => $query->whereHas('payments', fn ($q) => $q->where('status', 'pending_verification')),
            'none' => $query->whereDoesntHave('dues'),
            default => null,
        };
    }

    // ------------------------------------------------------------------ delete (draft only)

    public function confirmDelete($id)
    {
        $this->confirmingDeleteId = $id;
        $this->dispatchBrowserEvent('open-student-delete-modal');
    }

    public function delete()
    {
        abort_unless(Auth::user()->can('students.delete'), 403);

        $student = Student::find($this->confirmingDeleteId); // institute-scoped

        if (!$student) {
            $this->toast('danger', 'Student not found.');
        } elseif ($student->status !== 'draft') {
            $this->toast('warning', "Only draft students can be deleted. {$student->full_name} is {$student->status_label}.");
        } else {
            $this->auditDelete($student, 'students', "Deleted draft student: {$student->full_name}");
            foreach ($student->documents as $document) {
                Storage::disk('local')->delete($document->file_path);
                $document->delete();
            }
            $student->delete();
            $this->toast('danger', 'Draft student deleted.');
        }

        $this->confirmingDeleteId = null;
        $this->dispatchBrowserEvent('close-student-delete-modal');
    }

    protected function toast($type, $message)
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }

    // ------------------------------------------------------------------ render

    public function render()
    {
        $user = Auth::user();
        $isSuperAdmin = $user->isSuperAdmin();

        $students = $this->query()
            ->with(['course', 'institute', 'approvals', 'dues', 'payments', 'erRequest'])
            ->orderBy(in_array($this->sortField, self::SORTABLE, true) ? $this->sortField : 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate(in_array((int) $this->perPage, [10, 15, 25, 50], true) ? (int) $this->perPage : 15);

        // Courses for the filter: those offered by the chosen (or own) institute
        $instituteForCourses = $isSuperAdmin ? $this->institute : $user->institute_id;
        $courseIds = InstituteCourse::when($instituteForCourses, fn ($q) => $q->where('institute_id', $instituteForCourses))->pluck('course_id');

        // Summary cards (respect the institute filter of Super Admin)
        $base = fn () => Student::when($isSuperAdmin && $this->institute, fn ($q) => $q->where('institute_id', $this->institute));
        $summary = [
            'total' => $base()->count(),
            'pending_approval' => $base()->where('status', 'pending_approval')->count(),
            'er_issued' => $base()->whereIn('status', ['er_issued', 'active'])->count(),
            'unpaid' => $base()->whereHas('dues', fn ($q) => $q->whereIn('status', ['pending', 'partial']))->count(),
            'this_month' => $base()->where('created_at', '>=', now()->startOfMonth())->count(),
            // onboarding not finished (no ER number yet) and the deadline has passed
            'overdue' => $base()->whereNull('er_number')->where('status', '!=', 'rejected')->whereDate('onboarding_deadline', '<', today())->count(),
        ];

        $filters = array_filter([
            'search' => $this->search !== '' ? 'Search: “' . $this->search . '”' : null,
            'institute' => $isSuperAdmin && $this->institute ? optional(Institute::find($this->institute))->name : null,
            'course' => $this->course ? optional(Course::find($this->course))->code : null,
            'status' => $this->status ? 'Status: ' . config("camp.student_statuses.{$this->status}.0") : null,
            'docsGate' => $this->docsGate ? 'Docs: ' . (self::GATE_FILTERS[$this->docsGate] ?? '') : null,
            'feesGate' => $this->feesGate ? 'Fees gate: ' . (self::GATE_FILTERS[$this->feesGate] ?? '') : null,
            'payment' => $this->payment ? 'Payment: ' . (self::PAYMENT_FILTERS[$this->payment] ?? '') : null,
            'overdue' => $this->overdue ? 'Past deadline' : null,
        ]);

        return view('livewire.admin.students.students-component', [
            'students' => $students,
            'summary' => $summary,
            'activeFilters' => $filters,
            'isSuperAdmin' => $isSuperAdmin,
            'institutes' => $isSuperAdmin ? Institute::orderBy('name')->pluck('name', 'id') : collect(),
            'courses' => Course::whereIn('id', $courseIds)->orderBy('code')->get(['id', 'code', 'name']),
            'statuses' => collect(config('camp.student_statuses'))->map(fn ($s) => $s[0]),
            'canDelete' => $user->can('students.delete'),
            'canFees' => $user->can('fees.manage') || $user->can('payments.collect') || $user->can('payments.verify'),
        ])->layout('layouts.admin.master');
    }
}
