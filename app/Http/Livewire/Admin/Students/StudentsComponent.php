<?php

namespace App\Http\Livewire\Admin\Students;

use App\Models\Admin\Student;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

/**
 * Students list (Module 2). Institute users see only their institute (BelongsToInstitute scope).
 * Only draft students can be deleted; anything submitted stays for the record.
 */
class StudentsComponent extends Component
{
    use RecordsAuditTrail;

    public $confirmingDeleteId = null;

    protected $listeners = ['deleteRecord' => 'confirmDelete'];

    public function mount()
    {
        abort_unless(Auth::user()->can('students.view'), 403);
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('students.view'), 403);
    }

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
            $this->emit('refreshTable');
            $this->toast('danger', 'Draft student deleted.');
        }

        $this->confirmingDeleteId = null;
        $this->dispatchBrowserEvent('close-student-delete-modal');
    }

    protected function toast($type, $message)
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }

    public function render()
    {
        $user = Auth::user();

        $actions = [['route' => 'admin.students.edit', 'parameter' => 'student', 'parameter_value' => 'id', 'icon' => 'ti ti-edit', 'class' => 'btn-outline-warning', 'label' => 'Open onboarding']];
        if ($user->can('fees.manage') || $user->can('payments.collect') || $user->can('payments.verify')) {
            $actions[] = ['route' => 'admin.students.fees', 'parameter' => 'student', 'parameter_value' => 'id', 'icon' => 'ti ti-cash', 'class' => 'btn-outline-success', 'label' => 'Fees & payments'];
        }
        $actions[] = ['route' => 'admin.students.enrollment', 'parameter' => 'student', 'parameter_value' => 'id', 'icon' => 'ti ti-id', 'class' => 'btn-outline-info', 'label' => 'Gates, ER & ID card'];
        if ($user->can('students.delete')) {
            $actions[] = 'delete';
        }

        $columns = [
            ['label' => '#', 'field' => 'id', 'sortable' => true],
            ['label' => 'First Name', 'field' => 'first_name', 'sortable' => true],
            ['label' => 'Last Name', 'field' => 'last_name', 'sortable' => true],
            ['label' => 'Email', 'field' => 'email', 'sortable' => false],
            ['label' => 'Phone', 'field' => 'phone', 'sortable' => false],
            ['label' => 'Course', 'field' => 'course.code', 'sortable' => false],
        ];
        if ($user->isSuperAdmin()) {
            $columns[] = ['label' => 'Institute', 'field' => 'institute.name', 'sortable' => false];
        }
        $columns = array_merge($columns, [
            ['label' => 'ER Number', 'field' => 'er_number', 'sortable' => true],
            ['label' => 'Joining', 'field' => 'formatted_joining_date', 'sortable' => false, 'searchable' => false],
            ['label' => 'Onboarding Deadline', 'field' => 'formatted_onboarding_deadline', 'sortable' => false, 'searchable' => false],
            ['label' => 'Status', 'field' => 'status_html', 'type' => 'html', 'sortable' => false, 'searchable' => false],
            ['label' => 'Fees', 'field' => 'fee_status_html', 'type' => 'html', 'sortable' => false, 'searchable' => false],
            ['label' => 'Gate 1 · Docs', 'field' => 'documents_gate_html', 'type' => 'html', 'sortable' => false, 'searchable' => false],
            ['label' => 'Gate 2 · Fees', 'field' => 'fees_gate_html', 'type' => 'html', 'sortable' => false, 'searchable' => false],
            ['label' => 'Actions', 'field' => 'actions', 'type' => 'actions', 'actions' => $actions],
        ]);

        return view('livewire.admin.students.students-component', [
            'columns' => $columns,
            'statusFilters' => ['All' => 'All'] + collect(config('camp.student_statuses'))->map(fn ($s) => $s[0])->all(),
        ])->layout('layouts.admin.master');
    }
}
