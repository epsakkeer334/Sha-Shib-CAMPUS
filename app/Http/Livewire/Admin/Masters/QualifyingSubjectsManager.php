<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\QualifyingSubject;
use Illuminate\Validation\Rule;

/**
 * Master Data → 12th Subjects (course eligibility, Module 2 step 2.7).
 * Higher Secondary subjects such as Physics or Accountancy — not the Module 3 course subjects.
 */
class QualifyingSubjectsManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return QualifyingSubject::class;
    }

    protected function module(): string
    {
        return 'qualifying_subjects';
    }

    protected function title(): string
    {
        return '12th Subjects';
    }

    protected function singular(): string
    {
        return '12th Subject';
    }

    protected function icon(): string
    {
        return 'ti ti-book-2';
    }

    protected function group(): string
    {
        return 'Academic';
    }

    protected function fields(): array
    {
        return [
            'name' => [
                'label' => 'Subject name',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:100', Rule::unique('qualifying_subjects', 'name')->ignore($this->recordId)],
                'placeholder' => 'e.g. Physics',
            ],
            'code' => [
                'label' => 'Code',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('qualifying_subjects', 'code')->ignore($this->recordId)],
                'placeholder' => 'e.g. PHY',
                'help' => 'Class 12 subject used in course eligibility — not the subjects taught in a course.',
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Subject', 'field' => 'name', 'sortable' => true],
            ['label' => 'Code', 'field' => 'code', 'sortable' => true],
        ];
    }

    public function save()
    {
        $this->form['code'] = strtoupper(trim((string) ($this->form['code'] ?? '')));
        parent::save();
    }
}
