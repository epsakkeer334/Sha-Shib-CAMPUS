<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\QualifyingSubject;
use App\Models\Admin\QualifyingSubjectGroup;
use Illuminate\Validation\Rule;

/**
 * Master Data → 12th Subject Groups (course eligibility, Module 2 step 2.7), e.g.
 * PCM = Physics, Chemistry, Mathematics. Students choose a group as their 12th stream.
 */
class QualifyingSubjectGroupsManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return QualifyingSubjectGroup::class;
    }

    protected function module(): string
    {
        return 'qualifying_subject_groups';
    }

    protected function title(): string
    {
        return '12th Subject Groups';
    }

    protected function singular(): string
    {
        return '12th Subject Group';
    }

    protected function icon(): string
    {
        return 'ti ti-books';
    }

    protected function group(): string
    {
        return 'Academic';
    }

    protected function fields(): array
    {
        $group = $this->recordId ? QualifyingSubjectGroup::find($this->recordId) : null;
        $codeLocked = $group && $group->isInUse(); // students store the code

        return [
            'code' => [
                'label' => 'Code',
                'type' => 'text',
                'rules' => $codeLocked
                    ? ['required', Rule::in([$group->code])]
                    : ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('qualifying_subject_groups', 'code')->ignore($this->recordId)],
                'placeholder' => 'e.g. PCM',
                'col' => 4,
                'help' => $codeLocked ? 'In use by students or criteria, so the code cannot change.' : 'Short name students pick as their 12th stream.',
            ],
            'name' => [
                'label' => 'Name',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:150'],
                'placeholder' => 'e.g. Physics, Chemistry, Mathematics',
                'col' => 8,
            ],
            'subject_ids' => [
                'label' => 'Subjects in this group',
                'type' => 'checkboxes',
                'virtual' => true,
                'rules' => ['required', 'array', 'min:1'],
                'item_rules' => [Rule::exists('qualifying_subjects', 'id')->whereNull('deleted_at')],
                'options' => fn () => QualifyingSubject::active()->orderBy('name')->pluck('name', 'id')->all()
                    + ($group ? $group->subjects()->pluck('qualifying_subjects.name', 'qualifying_subjects.id')->all() : []),
                'col' => 12,
                'help' => 'Course eligibility criteria set a minimum mark for these subjects.',
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Code', 'field' => 'code', 'sortable' => true],
            ['label' => 'Name', 'field' => 'name', 'sortable' => true],
            ['label' => 'Subjects', 'field' => 'subject_list', 'sortable' => false, 'searchable' => false],
        ];
    }

    protected function loadExtra($record): void
    {
        $this->form['subject_ids'] = $record->subjects()->pluck('qualifying_subjects.id')->map(fn ($id) => (string) $id)->all();
    }

    protected function afterSave($record): void
    {
        $sync = collect($this->form['subject_ids'] ?? [])->values()->mapWithKeys(fn ($id, $i) => [(int) $id => ['sort_order' => $i + 1]])->all();
        $record->subjects()->sync($sync);
    }

    public function save()
    {
        $this->form['code'] = strtoupper(trim((string) ($this->form['code'] ?? '')));
        parent::save();
    }
}
