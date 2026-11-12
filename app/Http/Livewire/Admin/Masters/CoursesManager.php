<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\Course;
use App\Models\Admin\Student;
use Illuminate\Validation\Rule;
use Livewire\WithPagination;

/**
 * Master Data → Academic → Courses. Own list (code, name, structure, institutes, students),
 * shared add / edit / delete from MasterCrudComponent.
 */
class CoursesManager extends MasterCrudComponent
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $search = '';
    public $filterStatus = ''; // '' | active | inactive | unassigned

    protected $queryString = ['search' => ['except' => ''], 'filterStatus' => ['except' => '', 'as' => 'show']];

    public function updated($property)
    {
        if (in_array($property, ['search', 'filterStatus'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'filterStatus']);
        $this->resetPage();
    }

    /** Status switch in the list: same change and audit as Edit → Status. */
    public function toggleStatus($id)
    {
        $course = Course::findOrFail($id);
        $old = ['status' => $course->status];
        $course->update(['status' => !$course->status]);
        $this->auditUpdate($course, $this->module(), $old, ['status' => $course->status], 'Updated Course: ' . $this->recordLabel($course));
        $this->toast('success', $course->code . ($course->status ? ' is active again.' : ' is inactive: hidden from new course choices, kept on existing records.'));
    }

    protected function listView(): string
    {
        return 'livewire.admin.masters.courses';
    }

    protected function listData(): array
    {
        $courses = Course::query()
            ->withCount([
                'instituteCourses as offerings_count',
                'instituteCourses as active_offerings_count' => fn ($q) => $q->where('status', true),
                'students',
                'eligibilityCriteria',
            ])
            ->with(['instituteCourses' => fn ($q) => $q->with('institute:id,name,code')->orderByDesc('status')])
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($this->filterStatus === 'active', fn ($q) => $q->where('status', true))
            ->when($this->filterStatus === 'inactive', fn ($q) => $q->where('status', false))
            ->when($this->filterStatus === 'unassigned', fn ($q) => $q->doesntHave('instituteCourses'))
            ->orderBy('code')
            ->paginate(15);

        return [
            'courses' => $courses,
            'stats' => [
                'total' => Course::count(),
                'active' => Course::where('status', true)->count(),
                'inactive' => Course::where('status', false)->count(),
                'unassigned' => Course::doesntHave('instituteCourses')->count(),
                'students' => Student::whereNotNull('course_id')->count(),
            ],
            'hasFilters' => trim($this->search) !== '' || $this->filterStatus !== '',
        ];
    }
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
