<?php

namespace App\Http\Livewire\Admin\Onboarding;

use App\Models\Admin\Course;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Module 2.4 / 2.5 — ER & ID cards queue: every student with an ER number and where their
 * ER request form and ID card stand. Status cards (All / ER forms / ID cards / Completed), a filter
 * bar, and rows still needing work highlighted first. "Open" goes to the per-student ER & ID page
 * under this menu (admin.onboarding.enrollment.student).
 */
class EnrollmentQueueComponent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    const PER_PAGE = 15;

    const FORM_STAGES = ['generated' => 'To print', 'printed' => 'Awaiting TM signature', 'signed' => 'Signed — to archive', 'archived' => 'Archived'];
    const CARD_STAGES = ['to_print' => 'To print', 'to_sign' => 'Printed — to sign', 'reprinted' => 'Reprint — to sign', 'issued' => 'Issued'];

    public $tab = 'all';
    public $search = '';
    public $institute = '';
    public $course = '';
    public $formStage = '';
    public $cardStage = '';
    public $issuedFrom = '';
    public $issuedTo = '';

    protected $queryString = [
        'tab' => ['except' => 'all'], 'search' => ['except' => ''], 'institute' => ['except' => ''], 'course' => ['except' => ''],
        'formStage' => ['except' => ''], 'cardStage' => ['except' => ''], 'issuedFrom' => ['except' => ''], 'issuedTo' => ['except' => ''],
    ];

    public function mount()
    {
        abort_unless(Auth::user()->can('enrollment.manage'), 403);
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('enrollment.manage'), 403);
    }

    public function updated($property)
    {
        if (in_array($property, ['tab', 'search', 'institute', 'course', 'formStage', 'cardStage', 'issuedFrom', 'issuedTo'], true)) {
            $this->resetPage();
        }
        if ($property === 'institute') {
            $this->course = '';
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'institute', 'course', 'formStage', 'cardStage', 'issuedFrom', 'issuedTo']);
        $this->resetPage();
    }

    protected function base()
    {
        $isSuperAdmin = Auth::user()->isSuperAdmin();

        return Student::whereNotNull('er_number')
            ->when($isSuperAdmin && $this->institute, fn ($q) => $q->where('institute_id', $this->institute));
    }

    protected function tabFilter($query, string $tab)
    {
        return match ($tab) {
            // ER form still to be printed / signed / archived
            'form' => $query->whereHas('erRequest', fn ($q) => $q->whereNull('archived_at')),
            // ID card not yet signed & issued
            'card' => $query->whereHas('idCard', fn ($q) => $q->where('tm_signature_status', 'pending')),
            // everything done
            'done' => $query->whereHas('erRequest', fn ($q) => $q->whereNotNull('archived_at'))
                ->whereHas('idCard', fn ($q) => $q->where('tm_signature_status', 'physically_signed')),
            default => $query,
        };
    }

    protected function applyFilters($query)
    {
        if ($this->course) {
            $query->where('course_id', $this->course);
        }
        if (isset(self::FORM_STAGES[$this->formStage])) {
            $query->whereHas('erRequest', fn ($q) => $q->where('status', $this->formStage));
        }
        match ($this->cardStage) {
            'to_print' => $query->whereHas('idCard', fn ($q) => $q->where('tm_signature_status', 'pending')->where('status', '!=', 'reprinted')->where('print_count', 0)),
            'to_sign' => $query->whereHas('idCard', fn ($q) => $q->where('tm_signature_status', 'pending')->where('status', '!=', 'reprinted')->where('print_count', '>', 0)),
            'reprinted' => $query->whereHas('idCard', fn ($q) => $q->where('status', 'reprinted')),
            'issued' => $query->whereHas('idCard', fn ($q) => $q->where('tm_signature_status', 'physically_signed')),
            default => null,
        };
        if ($this->issuedFrom) {
            $query->whereHas('erRequest', fn ($q) => $q->whereDate('generated_at', '>=', $this->issuedFrom));
        }
        if ($this->issuedTo) {
            $query->whereHas('erRequest', fn ($q) => $q->whereDate('generated_at', '<=', $this->issuedTo));
        }
        if ($term = trim($this->search)) {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$term}%"])
                ->orWhere('er_number', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
        }

        return $query;
    }

    /** True while the ER form or the ID card still needs work. */
    public static function needsAction(Student $student): bool
    {
        return !optional($student->erRequest)->archived_at || optional($student->idCard)->tm_signature_status !== 'physically_signed';
    }

    public function render()
    {
        $user = Auth::user();
        $isSuperAdmin = $user->isSuperAdmin();

        $query = $this->applyFilters($this->tabFilter($this->base()->with(['course', 'institute', 'erRequest', 'idCard', 'documents']), $this->tab));

        // Still needing work first (ER form not archived or card not issued), then the newest ER numbers
        $students = $query
            ->orderByRaw("CASE WHEN EXISTS (SELECT 1 FROM er_requests r WHERE r.student_id = students.id AND r.archived_at IS NULL AND r.deleted_at IS NULL)
                OR EXISTS (SELECT 1 FROM id_cards c WHERE c.student_id = students.id AND c.tm_signature_status = 'pending' AND c.deleted_at IS NULL) THEN 0 ELSE 1 END")
            ->orderByDesc('er_number')
            ->paginate(self::PER_PAGE);

        $instituteForCourses = $isSuperAdmin ? $this->institute : $user->institute_id;
        $courseIds = InstituteCourse::when($instituteForCourses, fn ($q) => $q->where('institute_id', $instituteForCourses))->pluck('course_id');

        return view('livewire.admin.onboarding.enrollment-queue-component', [
            'students' => $students,
            'counts' => [
                'all' => $this->base()->count(),
                'form' => $this->tabFilter($this->base(), 'form')->count(),
                'formToSign' => $this->base()->whereHas('erRequest', fn ($q) => $q->where('status', 'printed'))->count(),
                'card' => $this->tabFilter($this->base(), 'card')->count(),
                'done' => $this->tabFilter($this->base(), 'done')->count(),
                'issuedThisMonth' => $this->base()->whereHas('erRequest', fn ($q) => $q->where('generated_at', '>=', now()->startOfMonth()))->count(),
            ],
            'isSuperAdmin' => $isSuperAdmin,
            'institutes' => $isSuperAdmin ? Institute::orderBy('name')->pluck('name', 'id') : collect(),
            'courses' => Course::whereIn('id', $courseIds)->orderBy('code')->get(['id', 'code', 'name']),
            'hasFilters' => $this->search || $this->institute || $this->course || $this->formStage || $this->cardStage || $this->issuedFrom || $this->issuedTo,
        ])->layout('layouts.admin.master');
    }
}
