<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\Country;
use Illuminate\Validation\Rule;

class CountriesManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return Country::class;
    }

    protected function module(): string
    {
        return 'countries';
    }

    protected function title(): string
    {
        return 'Countries';
    }

    protected function singular(): string
    {
        return 'Country';
    }

    protected function icon(): string
    {
        return 'ti ti-world';
    }

    protected function group(): string
    {
        return 'Location';
    }

    protected function fields(): array
    {
        return [
            'name' => [
                'label' => 'Country Name',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:255', Rule::unique('countries', 'name')->ignore($this->recordId)],
                'placeholder' => 'e.g. India',
            ],
            'code' => [
                'label' => 'Country Code',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:10', Rule::unique('countries', 'code')->ignore($this->recordId)],
                'placeholder' => 'e.g. IN',
                'col' => 3,
            ],
            'phone_code' => [
                'label' => 'Phone Code',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:10'],
                'placeholder' => 'e.g. +91',
                'col' => 3,
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Country', 'field' => 'name', 'sortable' => true],
            ['label' => 'Code', 'field' => 'code', 'sortable' => true],
            ['label' => 'Phone Code', 'field' => 'phone_code', 'sortable' => false],
        ];
    }
}
