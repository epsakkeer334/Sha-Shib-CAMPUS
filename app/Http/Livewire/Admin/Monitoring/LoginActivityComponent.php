<?php

namespace App\Http\Livewire\Admin\Monitoring;

use App\Models\Admin\Institute;
use App\Models\Admin\LoginLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Monitoring ▸ Login Activity (Super Admin): sign-ins, failed / blocked attempts and sign-outs of the admin panel
 * and the admissions portal (App\Services\LoginLogger), with today's figures, a 14-day chart, a watch list of
 * repeated failures and filters (search, panel, result, who, institute, dates, one person).
 */
class LoginActivityComponent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    const PER_PAGE = 30;

    /** Watch list: this many failed / blocked attempts within 24 hours from one IP or email. */
    const WATCH_MIN = 3;

    const WHO = ['staff' => 'Staff & admins', 'student' => 'Students', 'unknown' => 'Unknown accounts'];

    public $search = '';
    public $filterPanel = '';
    public $filterEvent = '';
    public $filterWho = '';
    public $filterInstitute = '';
    public $filterUser = '';
    public $dateFrom = '';
    public $dateTo = '';

    protected $queryString = [
        'search' => ['except' => ''], 'filterPanel' => ['except' => '', 'as' => 'panel'], 'filterEvent' => ['except' => '', 'as' => 'result'],
        'filterWho' => ['except' => '', 'as' => 'who'], 'filterInstitute' => ['except' => '', 'as' => 'institute'],
        'filterUser' => ['except' => '', 'as' => 'user'], 'dateFrom' => ['except' => '', 'as' => 'from'], 'dateTo' => ['except' => '', 'as' => 'to'],
    ];

    const FILTERS = ['search', 'filterPanel', 'filterEvent', 'filterWho', 'filterInstitute', 'filterUser', 'dateFrom', 'dateTo'];

    public function mount()
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);
    }

    public function updating($property)
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }
    }

    public function clearFilters()
    {
        $this->reset(self::FILTERS);
        $this->resetPage();
    }

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

    /** Show only one person's activity (click on a name). */
    public function person($userId)
    {
        $this->filterUser = (string) (int) $userId;
        $this->search = '';
        $this->resetPage();
    }

    /** Watch list / IP click: search for this IP or email. */
    public function lookFor($term)
    {
        $this->reset(['filterUser']);
        $this->search = (string) $term;
        $this->resetPage();
    }

    protected function parseDate($value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function filtered()
    {
        $from = $this->parseDate($this->dateFrom);
        $to = $this->parseDate($this->dateTo);
        $studentIds = fn () => DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'student')->where('model_has_roles.model_type', User::class)->select('model_has_roles.model_id');

        return LoginLog::query()
            ->when(array_key_exists($this->filterPanel, LoginLog::PANELS), fn ($q) => $q->where('panel', $this->filterPanel))
            ->when($this->filterEvent === 'problems', fn ($q) => $q->whereIn('event', ['failed', 'blocked']))
            ->when(array_key_exists($this->filterEvent, LoginLog::EVENTS), fn ($q) => $q->where('event', $this->filterEvent))
            ->when($this->filterWho === 'student', fn ($q) => $q->whereIn('user_id', $studentIds()))
            ->when($this->filterWho === 'staff', fn ($q) => $q->whereNotNull('user_id')->whereNotIn('user_id', $studentIds()))
            ->when($this->filterWho === 'unknown', fn ($q) => $q->whereNull('user_id'))
            ->when($this->filterInstitute === 'central', fn ($q) => $q->whereNull('institute_id'))
            ->when($this->filterInstitute && $this->filterInstitute !== 'central', fn ($q) => $q->where('institute_id', (int) $this->filterInstitute))
            ->when($this->filterUser, fn ($q) => $q->where('user_id', (int) $this->filterUser))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->when(trim($this->search), fn ($q, $term) => $q->where(fn ($w) => $w->where('email', 'like', "%{$term}%")
                ->orWhere('ip', 'like', "{$term}%")->orWhere('reason', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"))));
    }

    /** Today's figures. */
    protected function today(): array
    {
        $today = LoginLog::where('created_at', '>=', today());
        $byEvent = (clone $today)->selectRaw('event, COUNT(*) as total')->groupBy('event')->pluck('total', 'event');
        $byPanel = (clone $today)->where('event', 'login')->selectRaw('panel, COUNT(*) as total')->groupBy('panel')->pluck('total', 'panel');
        $online = User::online()->with('roles')->get(['id']);
        $onlineStudents = $online->filter(fn ($u) => $u->roles->contains('name', 'student'))->count();

        return [
            'logins' => (int) ($byEvent['login'] ?? 0),
            'admin' => (int) ($byPanel['admin'] ?? 0),
            'portal' => (int) ($byPanel['portal'] ?? 0),
            'failed' => (int) ($byEvent['failed'] ?? 0),
            'blocked' => (int) ($byEvent['blocked'] ?? 0),
            'people' => (clone $today)->where('event', 'login')->distinct()->count('user_id'),
            'online' => $online->count(),
            'online_students' => $onlineStudents,
            'online_staff' => $online->count() - $onlineStudents,
        ];
    }

    /** Sign-ins and failed / blocked attempts per day, last 14 days. */
    protected function chart(): array
    {
        $from = today()->subDays(13);
        $rows = LoginLog::where('created_at', '>=', $from)->whereIn('event', ['login', 'failed', 'blocked'])
            ->selectRaw("DATE(created_at) as d, SUM(event = 'login') as ok, SUM(event <> 'login') as bad")
            ->groupBy('d')->get()->keyBy('d');

        $days = collect(range(0, 13))->map(function ($i) use ($from, $rows) {
            $day = $from->copy()->addDays($i);
            $row = $rows->get($day->toDateString());

            return ['day' => $day, 'ok' => (int) optional($row)->ok, 'bad' => (int) optional($row)->bad];
        });

        return ['days' => $days, 'max' => max(1, $days->max(fn ($d) => $d['ok'] + $d['bad']))];
    }

    /** IPs and emails with repeated failed / blocked attempts in the last 24 hours. */
    protected function watchList()
    {
        $recent = fn () => LoginLog::whereIn('event', ['failed', 'blocked'])->where('created_at', '>=', now()->subDay());

        $ips = $recent()->whereNotNull('ip')->selectRaw('ip as term, COUNT(*) as total, COUNT(DISTINCT email) as accounts, MAX(created_at) as last_at')
            ->groupBy('ip')->having('total', '>=', self::WATCH_MIN)->orderByDesc('total')->limit(5)->get()->map(fn ($r) => ['type' => 'ip'] + $r->toArray());
        $emails = $recent()->whereNotNull('email')->selectRaw('email as term, COUNT(*) as total, COUNT(DISTINCT ip) as accounts, MAX(created_at) as last_at')
            ->groupBy('email')->having('total', '>=', self::WATCH_MIN)->orderByDesc('total')->limit(5)->get()->map(fn ($r) => ['type' => 'email'] + $r->toArray());

        return $ips->concat($emails)->sortByDesc('total')->take(6)->values();
    }

    /** The person picked with ?user= : summary card. */
    protected function personCard(): ?array
    {
        if (!$this->filterUser || !($user = User::with(['roles', 'institute'])->find((int) $this->filterUser))) {
            return null;
        }
        $logs = LoginLog::where('user_id', $user->id);
        $last = (clone $logs)->where('event', 'login')->latest('id')->first();

        return [
            'user' => $user,
            'last' => $last,
            'logins30' => (clone $logs)->where('event', 'login')->where('created_at', '>=', now()->subDays(30))->count(),
            'problems30' => (clone $logs)->whereIn('event', ['failed', 'blocked'])->where('created_at', '>=', now()->subDays(30))->count(),
            'devices' => (clone $logs)->where('event', 'login')->whereNotNull('browser')
                ->selectRaw("CONCAT(COALESCE(browser, ''), ' · ', COALESCE(platform, '')) as label, device, COUNT(*) as total")
                ->groupBy('label', 'device')->orderByDesc('total')->limit(4)->get(),
            'ips' => (clone $logs)->whereNotNull('ip')->distinct()->count('ip'),
        ];
    }

    /** Role label of a sign-in row. */
    public static function roleOf(?User $user): array
    {
        if (!$user) {
            return ['Unknown account', 'slate'];
        }
        if ($user->roles->contains('name', 'student')) {
            return ['Student', 'teal'];
        }
        if ($user->roles->contains('name', 'super-admin')) {
            return ['Super Admin', 'violet'];
        }

        return [$user->role_display_name ?: 'Staff', 'blue'];
    }

    public function render()
    {
        $logs = $this->filtered()->with(['user.roles', 'institute'])->orderByDesc('id')->paginate(self::PER_PAGE);

        return view('livewire.admin.monitoring.login-activity-component', [
            'logs' => $logs,
            'stats' => $this->today(),
            'chart' => $this->chart(),
            'watch' => $this->watchList(),
            'focus' => $this->personCard(),
            'institutes' => Institute::orderBy('name')->get(['id', 'name', 'code']),
            'hasFilters' => collect(self::FILTERS)->contains(fn ($f) => trim((string) $this->{$f}) !== ''),
        ])->layout('layouts.admin.master');
    }
}
