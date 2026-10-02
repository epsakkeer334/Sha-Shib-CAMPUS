<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\Qualification;
use Illuminate\Validation\Rule;

class QualificationsManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return Qualification::class;
    }

    protected function module(): string
    {
        return 'qualifications';
    }

    protected function title(): string
    {
        return 'Qualifications';
    }

    protected function singular(): string
    {
        return 'Qualification';
    }

    protected function icon(): string
    {
        return 'ti ti-certificate-2';
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
                'rules' => ['required', 'string', 'max:255', Rule::unique('qualifications', 'name')->ignore($this->recordId)],
                'placeholder' => 'e.g. Plus Two / 12th',
                'col' => 12,
            ],
        ];
    }
}
