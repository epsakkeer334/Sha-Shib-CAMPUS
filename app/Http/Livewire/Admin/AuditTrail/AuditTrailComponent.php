<?php

namespace App\Http\Livewire\Admin\AuditTrail;

use App\Models\Admin\AuditTrail;
use App\Models\Admin\Institute;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only audit trail with filters (search, institute, user, module, action, date range) and a
 * detail pop-up. Super Admin sees all institutes; Institute Admin only their own.
 */
class AuditTrailComponent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    const PER_PAGE = 25;

    public $search = '';
    public $filterInstitute = '';
    public $filterUser = '';
    public $filterModule = '';
    public $filterAction = '';
    public $dateFrom = '';
    public $dateTo = '';

    protected $queryString = [
        'search' => ['except' => ''], 'filterInstitute' => ['except' => '', 'as' => 'institute'],
        'filterUser' => ['except' => '', 'as' => 'user'], 'filterModule' => ['except' => '', 'as' => 'module'],
        'filterAction' => ['except' => '', 'as' => 'action'], 'dateFrom' => ['except' => '', 'as' => 'from'], 'dateTo' => ['except' => '', 'as' => 'to'],
    ];

    public $selected = null;
    public $showUnchanged = false; // detail pop-up: list fields that did not change too

    const FILTERS = ['search', 'filterInstitute', 'filterUser', 'filterModule', 'filterAction', 'dateFrom', 'dateTo'];

    public function mount()
    {
        abort_unless(Auth::user()->can('audit.view'), 403);
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('audit.view'), 403);
        if (!Auth::user()->isSuperAdmin()) {
            $this->filterInstitute = '';
        }
    }

    public function updating($property)
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }
    }

    public function updatedFilterInstitute()
    {
        $this->filterUser = '';
    }

    public function clearFilters()
    {
        $this->reset(self::FILTERS);
        $this->resetPage();
    }

    /** Quick date ranges: today | 7d | 30d */
    public function range($key)
    {
        $this->dateTo = today()->toDateString();
        $this->dateFrom = match ($key) {
            'today' => today()->toDateString(),
            '7d' => today()->subDays(6)->toDateString(),
            '30d' => today()->subDays(29)->toDateString(),
            default => '',
        };
        $this->resetPage();
    }

    protected function baseQuery()
    {
        $actor = Auth::user();

        return AuditTrail::visibleTo($actor)
            ->when($actor->isSuperAdmin() && $this->filterInstitute === 'central', fn ($q) => $q->whereNull('institute_id'))
            ->when($actor->isSuperAdmin() && $this->filterInstitute && $this->filterInstitute !== 'central', fn ($q) => $q->where('institute_id', $this->filterInstitute));
    }

    protected function filtered()
    {
        $from = $this->parseDate($this->dateFrom);
        $to = $this->parseDate($this->dateTo);

        return $this->baseQuery()
            ->when($this->filterUser === 'system', fn ($q) => $q->whereNull('user_id'))
            ->when($this->filterUser && $this->filterUser !== 'system', fn ($q) => $q->where('user_id', $this->filterUser))
            ->when($this->filterModule, fn ($q) => $q->where('module', $this->filterModule))
            ->when($this->filterAction, fn ($q) => $q->where('action', $this->filterAction))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->when(trim($this->search), function ($q, $term) {
                $q->where(function ($w) use ($term) {
                    $w->where('meta', 'like', "%{$term}%")->orWhere('ip_address', 'like', "%{$term}%")
                        ->orWhere('module', 'like', "%{$term}%")->orWhere('action', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                    if (ctype_digit($term)) {
                        $w->orWhere('reference_id', (int) $term)->orWhere('id', (int) $term);
                    }
                });
            });
    }

    protected function parseDate($value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function view($id)
    {
        $entry = AuditTrail::visibleTo(Auth::user())->with(['user', 'institute'])->findOrFail($id);
        $meta = $entry->meta ?? [];

        $this->selected = [
            'id' => $entry->id,
            'when' => $entry->formatted_created_at,
            'ago' => optional($entry->created_at)->diffForHumans(),
            'user' => optional($entry->user)->name ?? 'System',
            'email' => optional($entry->user)->email,
            'role' => $entry->user ? $entry->user->role_display_name : 'Automatic action',
            'institute' => optional($entry->institute)->name ?? 'Central',
            'action' => $entry->action,
            'module' => $entry->module,
            'reference' => $entry->reference_type ? Str::headline(class_basename($entry->reference_type)) . ' #' . $entry->reference_id : null,
            'ip' => $entry->ip_address,
            'description' => $meta['description'] ?? null,
            'reason' => $meta['reason'] ?? null,
            'old' => is_array($meta['old'] ?? null) ? $meta['old'] : [],
            'new' => is_array($meta['new'] ?? null) ? $meta['new'] : [],
            'extra' => collect($meta)->except(['description', 'reason', 'old', 'new'])->all(),
        ];

        $this->showUnchanged = false;
        $this->dispatchBrowserEvent('open-audit-modal');
    }

    /** Colour of an action chip. */
    public static function actionTone(string $action): string
    {
        return match (true) {
            in_array($action, ['create', 'issue', 'issue_er', 'generate', 'apply_fee', 'advance'], true) => 'green',
            in_array($action, ['update', 'sign', 'print', 'archive', 'remind'], true) => 'blue',
            in_array($action, ['delete', 'reject', 'revoke_advance', 'reprint'], true) => 'red',
            in_array($action, ['approve'], true) => 'teal',
            in_array($action, ['bypass', 'waive', 'reopen_gate', 'correct_marks'], true) => 'amber',
            default => 'slate',
        };
    }

    public function render()
    {
        $actor = Auth::user();
        $isSuperAdmin = $actor->isSuperAdmin();
        $base = $this->baseQuery();

        return view('livewire.admin.audit-trail.audit-trail-component', [
            'entries' => $this->filtered()->with(['user', 'institute'])->orderByDesc('id')->paginate(self::PER_PAGE),
            'isSuperAdmin' => $isSuperAdmin,
            'institutes' => $isSuperAdmin ? Institute::orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'users' => User::whereIn('id', (clone $base)->whereNotNull('user_id')->distinct()->pluck('user_id'))->orderBy('name')->get(['id', 'name']),
            'modules' => (clone $base)->distinct()->orderBy('module')->pluck('module')->filter()->values(),
            'actions' => (clone $base)->distinct()->orderBy('action')->pluck('action')->filter()->values(),
            'hasFilters' => collect(self::FILTERS)->contains(fn ($f) => trim((string) $this->{$f}) !== ''),
            'label' => fn ($value) => Str::of($value)->replace(['_', '-'], ' ')->title(),
        ])->layout('layouts.admin.master');
    }
}
