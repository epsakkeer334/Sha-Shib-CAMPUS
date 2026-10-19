<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\HigherSecondaryBoard;
use Illuminate\Validation\Rule;

class HigherSecondaryBoardsManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return HigherSecondaryBoard::class;
    }

    protected function module(): string
    {
        return 'higher_secondary_boards';
    }

    protected function title(): string
    {
        return 'Higher Secondary Boards';
    }

    protected function singular(): string
    {
        return 'Higher Secondary Board';
    }

    protected function icon(): string
    {
        return 'ti ti-building-bank';
    }

    protected function group(): string
    {
        return 'Academic';
    }

    protected function fields(): array
    {
        return [
            'name' => [
                'label' => 'Name',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:255', Rule::unique('higher_secondary_boards', 'name')->ignore($this->recordId)],
                'placeholder' => 'e.g. Kerala Higher Secondary Board',
                'col' => 8,
            ],
            'cgpa_factor' => [
                'label' => 'CGPA → % factor',
                'type' => 'number',
                'rules' => ['required', 'numeric', 'min:1', 'max:10'],
                'placeholder' => '9.5',
                'col' => 4,
                'help' => 'Course eligibility: % = CGPA × factor (CBSE uses 9.5).',
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Name', 'field' => 'name', 'sortable' => true],
            ['label' => 'CGPA factor', 'field' => 'cgpa_factor', 'sortable' => true],
        ];
    }

    public function openModal()
    {
        parent::openModal();
        $this->form['cgpa_factor'] = '9.5';
    }
}
