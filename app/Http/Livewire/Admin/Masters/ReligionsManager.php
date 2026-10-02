<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\Religion;
use Illuminate\Validation\Rule;

class ReligionsManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return Religion::class;
    }

    protected function module(): string
    {
        return 'religions';
    }

    protected function title(): string
    {
        return 'Religions';
    }

    protected function singular(): string
    {
        return 'Religion';
    }

    protected function icon(): string
    {
        return 'ti ti-users';
    }

    protected function group(): string
    {
        return 'Personal';
    }

    protected function fields(): array
    {
        return [
            'name' => [
                'label' => 'Name',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:255', Rule::unique('religions', 'name')->ignore($this->recordId)],
                'placeholder' => 'Religion name',
                'col' => 12,
            ],
        ];
    }
}
