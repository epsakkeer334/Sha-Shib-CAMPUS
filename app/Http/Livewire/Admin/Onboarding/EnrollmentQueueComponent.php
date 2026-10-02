<?php

namespace App\Http\Livewire\Admin\Onboarding;

use App\Models\Admin\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Module 2.4 / 2.5 — ER & ID cards queue: students with an ER number and where their
 * ER request form and ID card stand. Opens the per-student "Gates, ER & ID card" page.
 */
class EnrollmentQueueComponent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $tab = 'form';
    public $search = '';

    protected $queryString = ['tab' => ['except' => 'form']];

    public function mount()
    {
        abort_unless(Auth::user()->can('enrollment.manage'), 403);
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('enrollment.manage'), 403);
    }

    public function updated($property)
    {
        if (in_array($property, ['tab', 'search'], true)) {
            $this->resetPage();
        }
    }

    protected function base()
    {
        return Student::whereNotNull('er_number');
    }

    protected function filter($query, string $tab)
    {
        return match ($tab) {
            // ER form still to be printed / signed / archived
            'form' => $query->whereHas('erRequest', fn ($q) => $q->whereNull('archived_at')),
            // ID card not yet signed & issued
            'card' => $query->whereHas('idCard', fn ($q) => $q->where('tm_signature_status', 'pending')),
            // everything done
            default => $query->whereHas('erRequest', fn ($q) => $q->whereNotNull('archived_at'))
                ->whereHas('idCard', fn ($q) => $q->where('tm_signature_status', 'physically_signed')),
        };
    }

    public function render()
    {
        $query = $this->filter($this->base()->with(['course', 'erRequest', 'idCard']), $this->tab);

        if ($term = trim($this->search)) {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('er_number', 'like', "%{$term}%"));
        }

        return view('livewire.admin.onboarding.enrollment-queue-component', [
            'students' => $query->orderByDesc('updated_at')->paginate(15),
            'counts' => [
                'form' => $this->filter($this->base(), 'form')->count(),
                'card' => $this->filter($this->base(), 'card')->count(),
            ],
        ])->layout('layouts.admin.master');
    }
}
