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
                'col' => 12,
            ],
        ];
    }
}
