<?php

namespace App\Http\Livewire\Admin\Students;

use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\Student;
use App\Services\OnboardingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

/**
 * Module 2.4 / 2.5 — one student's dual gate, ER number, ER request form lifecycle and ID card.
 * Design: "Admin · ER number & ID card".
 */
class StudentEnrollmentComponent extends Component
{
    // NOT named $student: the route parameter is {student}.
    public $studentId;
    public $reprintReason = '';
    // Opened from the ER & ID cards queue (admin.onboarding.enrollment.student): back link + menu stay on the queue
    public $fromQueue = false;

    public function mount($student)
    {
        abort_unless(Auth::user()->can('students.view'), 403);
        $this->studentId = Student::findOrFail($student)->id; // institute scope → 404 for others
        $this->fromQueue = request()->routeIs('admin.onboarding.enrollment.student');
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('students.view'), 403);
    }

    protected function student(): Student
    {
        return Student::with(['course', 'institute', 'state', 'country', 'approvals.approver', 'erRequest.signer', 'erRequest.archiver', 'idCard.signer', 'documents', 'dues'])
            ->findOrFail($this->studentId);
    }

    protected function act(callable $action, string $message)
    {
        abort_unless(Auth::user()->can('enrollment.manage'), 403);

        try {
            $action($this->student(), app(OnboardingService::class));
            $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => $message]);
        } catch (RuntimeException $e) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'danger', 'message' => $e->getMessage()]);
        }
    }

    public function formPrinted()
    {
        $this->act(fn ($s, $svc) => $svc->markFormPrinted($this->required($s->erRequest)), 'ER request form marked as printed.');
    }

    public function formSigned()
    {
        $this->act(fn ($s, $svc) => $svc->markFormSigned($this->required($s->erRequest)), 'Marked as signed by the Training Manager.');
    }

    public function formArchived()
    {
        $this->act(fn ($s, $svc) => $svc->markFormArchived($this->required($s->erRequest)), 'Signed form archived in the physical file.');
    }

    public function cardPrinted()
    {
        $this->act(fn ($s, $svc) => $svc->markCardPrinted($this->required($s->idCard)), 'ID card print recorded.');
    }

    public function cardIssued()
    {
        $this->act(fn ($s, $svc) => $svc->issueCard($this->required($s->idCard)), 'ID card signed & issued. The student is now Active.');
    }

    public function cardReprint()
    {
        $this->validate(['reprintReason' => 'required|string|min:5|max:300'], [], ['reprintReason' => 'reason']);
        $this->act(fn ($s, $svc) => $svc->reprintCard($this->required($s->idCard), $this->reprintReason), 'Reprint started: print the new card and have it signed.');
        $this->reprintReason = '';
        $this->dispatchBrowserEvent('close-reprint-modal');
    }

    protected function required($model)
    {
        if (!$model) {
            throw new RuntimeException('The ER number has not been issued yet.');
        }

        return $model;
    }

    public function render()
    {
        $student = $this->student();
        $onboarding = app(OnboardingService::class);

        return view('livewire.admin.students.student-enrollment-component', [
            'student' => $student,
            'gates' => [
                EnrollmentApproval::DOCUMENTS => $student->gate(EnrollmentApproval::DOCUMENTS),
                EnrollmentApproval::FEES => $student->gate(EnrollmentApproval::FEES),
            ],
            'blockers' => [
                EnrollmentApproval::DOCUMENTS => $onboarding->gateBlocker($student, EnrollmentApproval::DOCUMENTS),
                EnrollmentApproval::FEES => $onboarding->gateBlocker($student, EnrollmentApproval::FEES),
            ],
            'form' => $student->erRequest,
            'card' => $student->idCard,
            'photo' => $student->documents->where('document_type', 'kyc_photo')->sortByDesc('uploaded_at')->first(),
            'canManage' => Auth::user()->can('enrollment.manage'),
            'fees' => [
                'total' => (float) $student->dues->where('status', '!=', 'waived')->sum('amount_due'),
                'paid' => (float) $student->dues->sum('amount_paid'),
                'outstanding' => $student->outstandingAmount(),
            ],
        ])->layout('layouts.admin.master');
    }
}
