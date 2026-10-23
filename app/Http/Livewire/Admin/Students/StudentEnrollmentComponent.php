<?php

namespace App\Http\Livewire\Admin\Students;

use App\Models\Admin\EnrollmentApproval;
use App\Models\Admin\Student;
use App\Services\ErNumberService;
use App\Services\OnboardingService;
use App\Services\PeriodService;
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
    // Training Manager who physically signed (ER form / ID card); preselected when a TM is logged in
    public $formSignerId = null;
    public $cardSignerId = null;
    // Opened from the ER & ID cards queue (admin.onboarding.enrollment.student): back link + menu stay on the queue
    public $fromQueue = false;

    // ER number management (step 2.8)
    public $erAction = 'assign';      // assign | change | clear
    public $erMode = 'existing';      // existing (type the number) | generate (next from the series)
    public $erValue = '';
    public $erSemester = '';
    public $erCardIssued = false;
    public $erFormArchived = false;
    public $erChargeFees = false;
    public $erNotify = false;
    public $erReason = '';
    public $openEr = false;           // ?assign=1 from the students list

    public function mount($student)
    {
        abort_unless(Auth::user()->can('students.view'), 403);
        $this->studentId = Student::findOrFail($student)->id; // institute scope → 404 for others
        $this->fromQueue = request()->routeIs('admin.onboarding.enrollment.student');

        if (Auth::user()->hasRole('training-manager')) {
            $this->formSignerId = $this->cardSignerId = Auth::id();
        }
        $this->openEr = request()->boolean('assign') && Auth::user()->can('students.manage_er');
    }

    // ------------------------------------------------------------------ ER number (step 2.8)

    public function openErModal($action = 'assign')
    {
        abort_unless(Auth::user()->can('students.manage_er'), 403);
        $this->resetValidation();
        $this->reset(['erMode', 'erValue', 'erCardIssued', 'erFormArchived', 'erChargeFees', 'erNotify', 'erReason']);
        $this->erAction = in_array($action, ['assign', 'change', 'clear'], true) ? $action : 'assign';
        $this->erSemester = (string) app(PeriodService::class)->currentPeriodNo($this->student());
        $this->dispatchBrowserEvent('open-er-modal');
    }

    public function saveEr()
    {
        abort_unless(Auth::user()->can('students.manage_er'), 403);
        $student = $this->student();
        $service = app(ErNumberService::class);
        $total = (int) optional($student->course)->total_periods ?: 1;
        abort_if($this->erAction === 'clear' && !Auth::user()->isSuperAdmin(), 403); // before try: HttpException is a RuntimeException

        try {
            if ($this->erAction === 'assign') {
                $this->validate([
                    'erMode' => 'required|in:existing,generate',
                    'erValue' => $this->erMode === 'existing' ? 'required|string|max:40' : 'nullable',
                    'erSemester' => "required|integer|min:1|max:{$total}",
                    'erReason' => 'nullable|string|max:300',
                ], [], ['erValue' => 'ER number', 'erSemester' => 'current semester']);
                if ($this->erMode === 'existing' && ($problem = $service->problem($this->erValue, $student))) {
                    $this->addError('erValue', $problem);

                    return;
                }
                $result = $service->assign($student, $this->erMode === 'generate' ? null : $this->erValue, [
                    'semester' => (int) $this->erSemester, 'card_issued' => (bool) $this->erCardIssued, 'form_archived' => (bool) $this->erFormArchived,
                    'charge_fees' => (bool) $this->erChargeFees, 'notify' => (bool) $this->erNotify, 'reason' => trim($this->erReason) ?: null,
                ]);
                $message = "ER number {$result['er_number']} given — {$student->full_name} is admitted as an existing student." . ($result['warnings'] ? ' ' . implode(' ', $result['warnings']) : '');
                $type = $result['warnings'] ? 'warning' : 'success';
            } elseif ($this->erAction === 'change') {
                $this->validate(['erValue' => 'required|string|max:40', 'erReason' => 'required|string|min:5|max:300'], [], ['erValue' => 'new ER number', 'erReason' => 'reason']);
                if ($problem = $service->problem($this->erValue, $student)) {
                    $this->addError('erValue', $problem);

                    return;
                }
                $new = $service->change($student, $this->erValue, trim($this->erReason));
                $message = "ER number changed to {$new}." . (optional($student->idCard)->print_count ? ' Reprint the ID card with the new number.' : '');
                $type = 'success';
            } else {
                $this->validate(['erReason' => 'required|string|min:5|max:300'], [], ['erReason' => 'reason']);
                $service->clear($student, trim($this->erReason));
                $message = 'ER number cleared.';
                $type = 'warning';
            }
        } catch (RuntimeException $e) {
            $this->addError($this->erAction === 'clear' ? 'erReason' : 'erValue', $e->getMessage());

            return;
        }

        $this->dispatchBrowserEvent('close-er-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('students.view'), 403);
    }

    protected function student(): Student
    {
        return Student::with(['course', 'institute', 'state', 'country', 'approvals.approver', 'erRequest.signer', 'erRequest.archiver', 'idCard.signer', 'documents', 'dues', 'erHistory.changer'])
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
        $this->act(fn ($s, $svc) => $svc->markFormSigned($this->required($s->erRequest), $this->formSignerId ? (int) $this->formSignerId : null), 'Marked as signed by the Training Manager.');
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
        $this->act(fn ($s, $svc) => $svc->issueCard($this->required($s->idCard), $this->cardSignerId ? (int) $this->cardSignerId : null), 'ID card signed & issued. The student is now Active.');
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
            'canManageEr' => Auth::user()->can('students.manage_er'),
            'canAssignEr' => ErNumberService::canAssign($student),
            'isSuperAdmin' => Auth::user()->isSuperAdmin(),
            'semesterOptions' => collect(range(1, max(1, (int) optional($student->course)->total_periods)))
                ->mapWithKeys(fn ($n) => [$n => optional($student->course)->periodName($n) . (app(PeriodService::class)->periodFor($student, $n) ? '' : ' (no calendar yet)')]),
            'trainingManagers' => $onboarding->trainingManagers($student),
            // TM: records their own signature; Super Admin / Institute Admin: choose the TM (dropdown)
            'canSign' => $onboarding->canRecordSignature($student),
            'chooseSigner' => $onboarding->choosesSigner(),
            'fees' => [
                'total' => (float) $student->dues->where('status', '!=', 'waived')->sum('amount_due'),
                'paid' => (float) $student->dues->sum('amount_paid'),
                'outstanding' => $student->outstandingAmount(),
            ],
        ])->layout('layouts.admin.master');
    }
}
