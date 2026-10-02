<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\State;
use App\Models\Admin\Country;
use Illuminate\Validation\Rule;

class StatesManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return State::class;
    }

    protected function module(): string
    {
        return 'states';
    }

    protected function title(): string
    {
        return 'States';
    }

    protected function singular(): string
    {
        return 'State';
    }

    protected function icon(): string
    {
        return 'ti ti-map-pin';
    }

    protected function group(): string
    {
        return 'Location';
    }

    protected function fields(): array
    {
        return [
            'country_id' => [
                'label' => 'Country',
                'type' => 'select',
                'rules' => ['required', Rule::exists('countries', 'id')->whereNull('deleted_at')],
                'options' => fn () => Country::active()->orderBy('name')->pluck('name', 'id')->all(),
            ],
            'name' => [
                'label' => 'State Name',
                'type' => 'text',
                'rules' => [
                    'required', 'string', 'max:255',
                    Rule::unique('states', 'name')->where('country_id', $this->form['country_id'] ?? null)->ignore($this->recordId),
                ],
                'placeholder' => 'e.g. Kerala',
            ],
            'code' => [
                'label' => 'State Code',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:10'],
                'placeholder' => 'e.g. KL',
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'State', 'field' => 'name', 'sortable' => true],
            ['label' => 'Code', 'field' => 'code', 'sortable' => true],
            ['label' => 'Country', 'field' => 'country.name', 'sortable' => false],
        ];
    }
}
