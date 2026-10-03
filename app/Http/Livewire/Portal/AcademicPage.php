<?php

namespace App\Http\Livewire\Portal;

use App\Http\Livewire\Portal\Concerns\StudentPortalPage;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\StudentAcademicDetail;
use App\Services\OnboardingService;
use App\Support\PortalProgress;
use App\Support\StudentRules;
use App\Traits\RecordsAuditTrail;
use Livewire\Component;

/**
 * Step 2 — "Academic" (design: "Website · Step 2 — Academic"). Editable until a fee payment is confirmed.
 */
class AcademicPage extends Component
{
    use StudentPortalPage, RecordsAuditTrail;

    public $matriculation_board_id, $matriculation_mark_type = 'percentage', $matriculation_mark;
    public $higher_secondary_board_id, $higher_secondary_subject, $higher_secondary_mark_type = 'percentage', $higher_secondary_mark;
    public $draftRestored = false;

    protected $step = 'academic';
    protected $draftFields = [
        'matriculation_board_id', 'matriculation_mark_type', 'matriculation_mark',
        'higher_secondary_board_id', 'higher_secondary_subject', 'higher_secondary_mark_type', 'higher_secondary_mark',
    ];

    public function mount()
    {
        $student = $this->student();

        if ($academic = $student->academicDetail) {
            $this->fill($academic->only($this->draftFields));
        }

        if ($this->canEditDetails($student)) {
            $this->draftRestored = $this->restoreDraft($student);
        }
        PortalProgress::remember($student, 'academic');
    }

    /**
     * Segmented controls / choice cards (only fixed values are accepted).
     */
    public function choose($field, $value)
    {
        $allowed = [
            'matriculation_mark_type' => array_keys(config('camp.mark_types')),
            'higher_secondary_mark_type' => array_keys(config('camp.mark_types')),
            'higher_secondary_subject' => array_keys(config('camp.higher_secondary_subjects')),
        ];

        if (isset($allowed[$field]) && in_array($value, $allowed[$field], true)) {
            $this->{$field} = $value;
            $this->resetValidation($field);
            $this->updated($field, $value); // keep the draft
        }
    }

    public function save()
    {
        return $this->store('portal.documents');
    }

    public function saveAndPay()
    {
        return $this->store(PortalProgress::paymentUnlocked($this->student()) ? 'portal.payment' : 'portal.documents');
    }

    protected function store(string $next)
    {
        $student = $this->student();

        if (!$this->canEditDetails($student)) {
            return redirect()->route($next);
        }

        $data = $this->validate(StudentRules::academic([
            'matriculation_mark_type' => $this->matriculation_mark_type,
            'higher_secondary_mark_type' => $this->higher_secondary_mark_type,
        ]), StudentRules::messages());

        $academic = StudentAcademicDetail::firstOrNew(['student_id' => $student->id]);
        $old = $academic->exists ? $academic->only(array_keys($data)) : [];
        $academic->fill($data)->save();
        $this->auditUpdate($student, 'students', $old, $data, "Student updated academic details on the portal: {$student->full_name}");

        if ($student->submitted_at) {
            app(OnboardingService::class)->detailsChanged($student, 'academic details');
        }

        PortalProgress::clearDraft($student, 'academic');
        session()->flash('toast', ['type' => 'success', 'message' => $next === 'portal.payment' ? 'Academic details saved.' : 'Academic details saved. Next: documents.']);

        return redirect()->route($next);
    }

    public function render()
    {
        $student = $this->student();

        return view('portal.academic', [
            'student' => $student,
            'readOnly' => !$this->canEditDetails($student),
            'matriculationBoards' => MatriculationBoard::active()->orderBy('name')->pluck('name', 'id'),
            'higherSecondaryBoards' => HigherSecondaryBoard::active()->orderBy('name')->pluck('name', 'id'),
            'streams' => [
                'PCM' => ['PCM', 'Physics, Chemistry, Maths'],
                'PCB' => ['PCB', 'Physics, Chemistry, Biology'],
                'COMMERCE' => ['Commerce', 'Accounts, Business'],
                'ARTS' => ['Arts', 'Humanities'],
            ],
        ])->layout('layouts.portal', ['title' => 'Academic details']);
    }
}
