<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\AcademicYear;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

/**
 * Master Data → Academic → Academic Years (Super Admin). Academic periods and period fee lines
 * refer to them (Module 2B). Years may not overlap.
 */
class AcademicYearsManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return AcademicYear::class;
    }

    protected function module(): string
    {
        return 'academic_years';
    }

    protected function title(): string
    {
        return 'Academic Years';
    }

    protected function singular(): string
    {
        return 'Academic Year';
    }

    protected function icon(): string
    {
        return 'ti ti-calendar-stats';
    }

    protected function group(): string
    {
        return 'Academic';
    }

    protected function fields(): array
    {
        return [
            'name' => [
                'label' => 'Academic Year',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{2}$/', Rule::unique('academic_years', 'name')->ignore($this->recordId)],
                'placeholder' => 'e.g. ' . AcademicYear::nameFor(AcademicYear::startFor(now())),
                'col' => 4,
            ],
            'start_date' => [
                'label' => 'Starts On',
                'type' => 'date',
                'rules' => ['required', 'date'],
                'col' => 4,
            ],
            'end_date' => [
                'label' => 'Ends On',
                'type' => 'date',
                'rules' => ['required', 'date', 'after:form.start_date', function ($attribute, $value, $fail) {
                    $start = $this->form['start_date'] ?? null;
                    if (!$start || !$value) {
                        return;
                    }
                    $overlap = AcademicYear::query()
                        ->when($this->recordId, fn ($q) => $q->whereKeyNot($this->recordId))
                        ->whereDate('start_date', '<=', Carbon::parse($value)->toDateString())
                        ->whereDate('end_date', '>=', Carbon::parse($start)->toDateString())
                        ->first();
                    if ($overlap) {
                        $fail("These dates overlap academic year {$overlap->name} ({$overlap->period_label}).");
                    }
                }],
                'col' => 4,
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Academic Year', 'field' => 'name', 'sortable' => true],
            ['label' => 'Starts On', 'field' => 'start_date', 'type' => 'datetime', 'format' => 'd M Y', 'sortable' => true],
            ['label' => 'Ends On', 'field' => 'end_date', 'type' => 'datetime', 'format' => 'd M Y', 'sortable' => true],
        ];
    }

    public function edit($id)
    {
        parent::edit($id);
        foreach (['start_date', 'end_date'] as $key) {
            $this->form[$key] = $this->form[$key] ? Carbon::parse($this->form[$key])->toDateString() : null;
        }
    }

    public function openModal()
    {
        parent::openModal();
        // suggest the year after the latest one
        $last = AcademicYear::orderByDesc('end_date')->first();
        $start = $last ? $last->end_date->copy()->addDay() : AcademicYear::startFor(now());
        $this->form['name'] = AcademicYear::nameFor($start);
        $this->form['start_date'] = $start->toDateString();
        $this->form['end_date'] = $start->copy()->addYear()->subDay()->toDateString();
    }
}
