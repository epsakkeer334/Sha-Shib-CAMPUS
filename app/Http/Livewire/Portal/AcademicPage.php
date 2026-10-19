<?php

namespace App\Http\Livewire\Portal;

use App\Http\Livewire\Portal\Concerns\StudentPortalPage;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\QualifyingSubjectGroup;
use App\Models\Admin\StudentAcademicDetail;
use App\Services\EligibilityService;
use App\Services\OnboardingService;
use App\Support\PortalProgress;
use App\Support\StudentRules;
use App\Traits\RecordsAuditTrail;
use Livewire\Component;

/**
 * Step 2 — "Academic" (design: "Website · Step 2 — Academic"). Editable until a fee payment is confirmed.
 * 12th: board → subject group (only the groups the course accepts) → a mark per subject, checked live
 * against the course eligibility criteria (Module 2 step 2.7).
 */
class AcademicPage extends Component
{
    use StudentPortalPage, RecordsAuditTrail;

    public $matriculation_board_id, $matriculation_mark_type = 'percentage', $matriculation_mark;
    public $higher_secondary_board_id, $higher_secondary_subject, $higher_secondary_mark_type = 'percentage', $higher_secondary_mark;
    /** @var array<int|string, mixed> 12th subject id => mark */
    public $subject_marks = [];
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
        $this->subject_marks = $student->qualifyingMarks()->get()
            ->mapWithKeys(fn ($m) => [$m->qualifying_subject_id => $this->plain($m->mark)])->all();

        if ($this->canEditDetails($student)) {
            $this->draftRestored = $this->restoreDraft($student);
        }
        PortalProgress::remember($student, 'academic');
    }

    protected function plain($mark)
    {
        return $mark === null ? null : rtrim(rtrim((string) $mark, '0'), '.');
    }

    /**
     * Segmented controls / choice cards (only fixed values are accepted).
     */
    public function choose($field, $value)
    {
        $allowed = [
            'matriculation_mark_type' => array_keys(config('camp.mark_types')),
            'higher_secondary_mark_type' => array_keys(config('camp.mark_types')),
            'higher_secondary_subject' => $this->groups()->pluck('code')->all(),
        ];

        if (isset($allowed[$field]) && in_array($value, $allowed[$field], true)) {
            $this->{$field} = $value;
            $this->resetValidation($field);
            $this->updated($field, $value); // keep the draft
        }
    }

    /** Subject groups the student's course accepts (+ the saved one). */
    protected function groups()
    {
        $saved = optional($this->student()->academicDetail)->higher_secondary_subject;

        return app(EligibilityService::class)->groupsFor($this->student()->course_id, $saved);
    }

    protected function entry(): array
    {
        return app(EligibilityService::class)->subjectsToEnter($this->student()->course_id, $this->higher_secondary_board_id ?: null, $this->higher_secondary_subject);
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

        $entry = $this->entry();
        $data = $this->validate(StudentRules::academic([
            'matriculation_mark_type' => $this->matriculation_mark_type,
            'higher_secondary_mark_type' => $this->higher_secondary_mark_type,
            'current_group' => optional($student->academicDetail)->higher_secondary_subject,
            'subject_rows' => $entry['subjects'],
            'subjects_required' => $entry['required'],
        ]), array_merge(StudentRules::messages(), StudentRules::subjectMessages($entry['subjects'])));
        $marks = array_intersect_key($data['subject_marks'] ?? [], array_flip(array_column($entry['subjects'], 'id')));
        unset($data['subject_marks']);
        $data['qualifying_subject_group_id'] = optional(QualifyingSubjectGroup::where('code', $data['higher_secondary_subject'])->first())->id;

        $academic = StudentAcademicDetail::firstOrNew(['student_id' => $student->id]);
        $old = $academic->exists ? $academic->only(array_keys($data)) : [];
        $academic->fill($data)->save();
        app(EligibilityService::class)->saveMarks($student->fresh(), $marks, $data['higher_secondary_mark_type']);
        $this->auditUpdate($student, 'students', $old, $data + ['subject_marks' => $marks], "Student updated academic details on the portal: {$student->full_name}");

        if ($student->submitted_at) {
            app(OnboardingService::class)->detailsChanged($student, 'academic details');
        }

        PortalProgress::clearDraft($student, 'academic');
        $status = optional($student->academicDetail()->first())->eligibility_status;
        session()->flash('toast', $status === 'not_eligible'
            ? ['type' => 'warning', 'message' => 'Academic details saved, but your 12th marks do not meet the course eligibility criteria yet.']
            : ['type' => 'success', 'message' => $next === 'portal.payment' ? 'Academic details saved.' : 'Academic details saved. Next: documents.']);

        return redirect()->route($next);
    }

    public function render()
    {
        $student = $this->student();
        $service = app(EligibilityService::class);
        $entry = $this->entry();

        return view('portal.academic', [
            'student' => $student,
            'readOnly' => !$this->canEditDetails($student),
            'matriculationBoards' => MatriculationBoard::active()->orderBy('name')->pluck('name', 'id'),
            'higherSecondaryBoards' => HigherSecondaryBoard::active()->orderBy('name')->pluck('name', 'id'),
            'groups' => $this->groups(),
            'criteria' => $service->criteriaFor($student->course_id),
            'entry' => $entry,
            'check' => $this->higher_secondary_subject ? $service->check([
                'course_id' => $student->course_id, 'board_id' => $this->higher_secondary_board_id ?: null, 'group_code' => $this->higher_secondary_subject,
                'mark_type' => $this->higher_secondary_mark_type, 'marks' => (array) $this->subject_marks,
            ]) : null,
        ])->layout('layouts.portal', ['title' => 'Academic details']);
    }
}
