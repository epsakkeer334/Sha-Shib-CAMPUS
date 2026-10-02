<?php

namespace App\Http\Livewire\Portal;

use App\Http\Livewire\Portal\Concerns\StudentPortalPage;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\StudentAcademicDetail;
use App\Support\StudentRules;
use App\Traits\RecordsAuditTrail;
use Livewire\Component;

/**
 * Step 2 — "Academic" (design: "Website · Step 2 — Academic").
 */
class AcademicPage extends Component
{
    use StudentPortalPage, RecordsAuditTrail;

    public $matriculation_board_id, $matriculation_mark_type = 'percentage', $matriculation_mark;
    public $higher_secondary_board_id, $higher_secondary_subject, $higher_secondary_mark_type = 'percentage', $higher_secondary_mark;

    public function mount()
    {
        if ($academic = $this->student()->academicDetail) {
            $this->fill($academic->only([
                'matriculation_board_id', 'matriculation_mark_type', 'matriculation_mark',
                'higher_secondary_board_id', 'higher_secondary_subject', 'higher_secondary_mark_type', 'higher_secondary_mark',
            ]));
        }
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
        }
    }

    public function save()
    {
        $student = $this->student();

        if (!$this->canEditDetails($student)) {
            return redirect()->route('portal.documents');
        }

        $data = $this->validate(StudentRules::academic([
            'matriculation_mark_type' => $this->matriculation_mark_type,
            'higher_secondary_mark_type' => $this->higher_secondary_mark_type,
        ]), StudentRules::messages());

        $academic = StudentAcademicDetail::firstOrNew(['student_id' => $student->id]);
        $old = $academic->exists ? $academic->only(array_keys($data)) : [];
        $academic->fill($data)->save();
        $this->auditUpdate($student, 'students', $old, $data, "Student updated academic details on the portal: {$student->full_name}");

        session()->flash('toast', ['type' => 'success', 'message' => 'Academic details saved. Next: documents.']);

        return redirect()->route('portal.documents');
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
