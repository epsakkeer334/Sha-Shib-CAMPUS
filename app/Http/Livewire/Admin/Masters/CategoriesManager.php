<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\Category;
use App\Models\Admin\Religion;
use Illuminate\Validation\Rule;

class CategoriesManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function module(): string
    {
        return 'categories';
    }

    protected function title(): string
    {
        return 'Categories';
    }

    protected function singular(): string
    {
        return 'Category';
    }

    protected function icon(): string
    {
        return 'ti ti-category';
    }

    protected function group(): string
    {
        return 'Personal';
    }

    protected function fields(): array
    {
        return [
            'religion_id' => [
                'label' => 'Religion',
                'type' => 'select',
                'rules' => ['required', Rule::exists('religions', 'id')->whereNull('deleted_at')],
                'options' => fn () => Religion::active()->orderBy('name')->pluck('name', 'id')->all(),
                'help' => 'Students see only the categories of the religion they select.',
            ],
            'name' => [
                'label' => 'Category Name',
                'type' => 'text',
                'rules' => [
                    'required', 'string', 'max:255',
                    Rule::unique('categories', 'name')->where('religion_id', $this->form['religion_id'] ?? null)->ignore($this->recordId),
                ],
                'placeholder' => 'e.g. OBC',
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Category', 'field' => 'name', 'sortable' => true],
            ['label' => 'Religion', 'field' => 'religion.name', 'sortable' => false],
        ];
    }
}
