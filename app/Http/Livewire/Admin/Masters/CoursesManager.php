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
            // Academic period structure (Module 2B): label × length × number of periods
            'period_label' => [
                'label' => 'Period type',
                'type' => 'select',
                'options' => fn () => config('camp.period_labels'),
                'rules' => ['required', \Illuminate\Validation\Rule::in(array_keys(config('camp.period_labels')))],
                'col' => 4,
            ],
            'period_months' => [
                'label' => 'Period length (months)',
                'type' => 'number',
                'rules' => ['nullable', 'integer', 'min:1', 'max:24'],
                'placeholder' => 'Blank = duration ÷ number of periods',
                'col' => 4,
            ],
            'total_semesters' => [
                'label' => 'Number of periods',
                'type' => 'number',
                'rules' => ['required', 'integer', 'min:1', 'max:40'],
                'placeholder' => 'e.g. 8 semesters, or 1 for a 3-month course',
                'col' => 4,
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
            ['label' => 'Periods', 'field' => 'period_summary', 'sortable' => false],
        ];
    }

    protected function resetForm()
    {
        parent::resetForm();
        $this->form['period_label'] = 'Semester';
    }

    public function save()
    {
        // blank period length: work it out from the course duration
        if (empty($this->form['period_months']) && !empty($this->form['duration_months']) && !empty($this->form['total_semesters'])) {
            $this->form['period_months'] = max(1, (int) round($this->form['duration_months'] / max(1, (int) $this->form['total_semesters'])));
        }

        return parent::save();
    }
}
