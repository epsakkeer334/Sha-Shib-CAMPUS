<?php

namespace App\Http\Livewire\Admin\Notifications;

use App\Models\Admin\Institute;
use App\Models\Admin\NotificationLog;
use App\Models\Admin\Student;
use App\Models\User;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Email & SMS Log: every email (EmailService → SMTP2GO) and SMS, with filters (search, institute, channel,
 * event, status, date range) and a pop-up showing the message as sent, SMTP2GO id, attempts and error.
 * Super Admin sees all institutes and can send pending / failed emails again; others only see their own institute.
 */
class NotificationLogComponent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    const PER_PAGE = 25;

    const STATUSES = ['sent' => ['Sent', 'green'], 'pending' => ['Pending', 'amber'], 'failed' => ['Failed', 'red']];
    const CHANNELS = ['email' => ['Email', 'ti ti-mail'], 'sms' => ['SMS', 'ti ti-message-2']];

    public $search = '';
    public $filterInstitute = '';
    public $filterChannel = '';
    public $filterEvent = '';
    public $filterStatus = '';
    public $dateFrom = '';
    public $dateTo = '';

    protected $queryString = [
        'search' => ['except' => ''], 'filterInstitute' => ['except' => '', 'as' => 'institute'],
        'filterChannel' => ['except' => '', 'as' => 'channel'], 'filterEvent' => ['except' => '', 'as' => 'event'],
        'filterStatus' => ['except' => '', 'as' => 'status'], 'dateFrom' => ['except' => '', 'as' => 'from'], 'dateTo' => ['except' => '', 'as' => 'to'],
    ];

    const FILTERS = ['search', 'filterInstitute', 'filterChannel', 'filterEvent', 'filterStatus', 'dateFrom', 'dateTo'];

    public $selected = null;

    public function mount()
    {
        abort_unless(Auth::user()->can('notifications.view'), 403);
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('notifications.view'), 403);
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

    protected function baseQuery()
    {
        $actor = Auth::user();

        return NotificationLog::visibleTo($actor)
            ->when($actor->isSuperAdmin() && $this->filterInstitute === 'central', fn ($q) => $q->whereNull('institute_id'))
            ->when($actor->isSuperAdmin() && $this->filterInstitute && $this->filterInstitute !== 'central', fn ($q) => $q->where('institute_id', $this->filterInstitute));
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

        return $this->baseQuery()
            ->when($this->filterChannel, fn ($q) => $q->where('channel', $this->filterChannel))
            ->when($this->filterEvent, fn ($q) => $q->where('event_type', $this->filterEvent))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->when(trim($this->search), fn ($q, $term) => $q->where(fn ($w) => $w->where('recipient', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%")->orWhere('payload', 'like', "%{$term}%")->orWhere('error', 'like', "%{$term}%")
                ->orWhereHasMorph('notifiable', [Student::class], fn ($s) => $s->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")->orWhere('er_number', 'like', "%{$term}%"))
                ->orWhereHasMorph('notifiable', [User::class], fn ($u) => $u->where('name', 'like', "%{$term}%"))));
    }

    public static function eventLabel(?string $event): string
    {
        if ($event && ($name = config("email_templates.templates.{$event}.name"))) {
            return $name;
        }
        if ($event === 'template_test') {
            return 'Template test';
        }
        if ($event === 'bulk_email') {
            return 'Send Email';
        }
        if ($event === 'bulk_email_test') {
            return 'Send Email test';
        }

        return $event ? Str::of($event)->replace('_', ' ')->title()->replace(['Er ', 'Id '], ['ER ', 'ID '])->__toString() : '—';
    }

    /** Who the notification is for: [name, type label, link or null]. */
    protected function recipientInfo(NotificationLog $log): array
    {
        $who = $log->notifiable;
        if ($who instanceof Student) {
            return [$who->full_name, 'Student' . ($who->er_number ? ' · ' . $who->er_number : ''), route('admin.students.fees', $who->id)];
        }
        if ($who instanceof User) {
            return [$who->name, 'Staff · ' . ($who->role_display_name ?? 'User'), null];
        }

        return [$log->recipient ?: '—', $log->notifiable_type ? class_basename($log->notifiable_type) . ' #' . $log->notifiable_id : '—', null];
    }

    public function view($id)
    {
        $log = NotificationLog::visibleTo(Auth::user())->with(['institute', 'notifiable'])->findOrFail($id);
        $payload = is_array($log->payload) ? $log->payload : [];
        [$name, $type, $link] = $this->recipientInfo($log);

        $this->selected = [
            'id' => $log->id,
            'event' => self::eventLabel($log->event_type),
            'channel' => $log->channel,
            'status' => $log->status,
            'recipient' => $log->recipient,
            'name' => $name,
            'type' => $type,
            'link' => $link,
            'institute' => optional($log->institute)->name ?? 'Central',
            'created' => optional($log->created_at)->format('d M Y, h:i:s A'),
            'ago' => optional($log->created_at)->diffForHumans(),
            'sent' => optional($log->sent_at)->format('d M Y, h:i:s A'),
            'error' => $log->error,
            'subject' => $log->subject ?? ($payload['subject'] ?? null),
            'message' => $payload['message'] ?? null,
            'html' => $payload['html'] ?? null,
            'from' => $payload['sender_name'] ?? null,
            'reply_to' => $payload['reply_to'] ?? null,
            'template' => $log->template_key ? (config("email_templates.templates.{$log->template_key}.name") ?? $log->template_key) : null,
            'campaign_id' => $log->email_campaign_id,
            'provider' => $log->provider,
            'provider_id' => $log->provider_message_id,
            'attempts' => (int) $log->attempts,
            'last_attempt' => optional($log->last_attempt_at)->format('d M Y, h:i:s A'),
            'can_retry' => $log->channel === 'email' && $log->status !== 'sent' && $log->recipient && Auth::user()->isSuperAdmin(),
            'extra' => collect($payload)->except(['subject', 'message', 'html', 'to_name', 'sender_name', 'reply_to'])->all(),
        ];

        $this->dispatchBrowserEvent('open-notification-log-modal');
    }

    /**
     * Super Admin: send a pending / failed email again (in the background, like any other email).
     */
    public function retry($id)
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);
        $log = NotificationLog::where('channel', 'email')->findOrFail($id);
        app(EmailService::class)->retry($log);

        $this->view($log->id);
        $this->dispatchBrowserEvent('show-toast', ['type' => $log->fresh()->error ? 'warning' : 'success',
            'message' => $log->fresh()->error ?: 'Email queued again — it is sent in the background.']);
    }

    /**
     * Super Admin: send every failed email in the current filters again (max 200 at a time).
     */
    public function retryFailed()
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);
        $emails = app(EmailService::class);
        $logs = $this->filtered()->where('channel', 'email')->where('status', 'failed')->whereNotNull('recipient')->orderBy('id')->limit(200)->get();
        $logs->each(fn ($log) => $emails->retry($log));

        $this->dispatchBrowserEvent('show-toast', ['type' => $logs->isEmpty() ? 'info' : 'success',
            'message' => $logs->isEmpty() ? 'No failed emails to send again.' : $logs->count() . ' failed ' . Str::plural('email', $logs->count()) . ' queued again.']);
    }

    public function render()
    {
        $actor = Auth::user();
        $isSuperAdmin = $actor->isSuperAdmin();
        $base = $this->baseQuery();
        $logs = $this->filtered()->with(['institute', 'notifiable'])->orderByDesc('id')->paginate(self::PER_PAGE);

        return view('livewire.admin.notifications.notification-log-component', [
            'logs' => $logs,
            'who' => fn (NotificationLog $log) => $this->recipientInfo($log),
            'isSuperAdmin' => $isSuperAdmin,
            'institutes' => $isSuperAdmin ? Institute::orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'events' => (clone $base)->distinct()->orderBy('event_type')->pluck('event_type')->filter()->values(),
            'statusCounts' => (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'hasFilters' => collect(self::FILTERS)->contains(fn ($f) => trim((string) $this->{$f}) !== ''),
        ])->layout('layouts.admin.master');
    }
}
