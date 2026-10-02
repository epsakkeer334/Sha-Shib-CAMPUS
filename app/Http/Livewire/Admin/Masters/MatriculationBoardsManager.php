<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\MatriculationBoard;
use Illuminate\Validation\Rule;

class MatriculationBoardsManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return MatriculationBoard::class;
    }

    protected function module(): string
    {
        return 'matriculation_boards';
    }

    protected function title(): string
    {
        return 'Matriculation Boards';
    }

    protected function singular(): string
    {
        return 'Matriculation Board';
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
                'rules' => ['required', 'string', 'max:255', Rule::unique('matriculation_boards', 'name')->ignore($this->recordId)],
                'placeholder' => 'e.g. CBSE',
                'col' => 12,
            ],
        ];
    }
}
