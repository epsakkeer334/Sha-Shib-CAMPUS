<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\Course;
use Illuminate\Validation\Rule;

class CoursesManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return Course::class;
    }

    protected function module(): string
    {
        return 'courses';
    }

    protected function title(): string
    {
        return 'Courses';
    }

    protected function singular(): string
    {
        return 'Course';
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
        return [
            'name' => [
                'label' => 'Course Name',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:255'],
                'placeholder' => 'e.g. Aircraft Maintenance Engineering — B1.1',
            ],
            'code' => [
                'label' => 'Course Code',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:50', Rule::unique('courses', 'code')->ignore($this->recordId)],
                'placeholder' => 'e.g. B1.1',
            ],
            'duration_months' => [
                'label' => 'Duration (months)',
                'type' => 'number',
                'rules' => ['required', 'integer', 'min:1', 'max:120'],
                'placeholder' => 'e.g. 48',
            ],
            'total_semesters' => [
                'label' => 'Total Semesters',
                'type' => 'number',
                'rules' => ['required', 'integer', 'min:1', 'max:20'],
                'placeholder' => 'e.g. 8',
            ],
            'description' => [
                'label' => 'Description',
                'type' => 'textarea',
                'rules' => ['nullable', 'string', 'max:2000'],
                'col' => 12,
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Course Name', 'field' => 'name', 'sortable' => true],
            ['label' => 'Code', 'field' => 'code', 'sortable' => true],
            ['label' => 'Duration (months)', 'field' => 'duration_months', 'sortable' => true],
            ['label' => 'Semesters', 'field' => 'total_semesters', 'sortable' => true],
        ];
    }
}
