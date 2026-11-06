<?php

namespace App\Http\Livewire\Admin\Emails;

use App\Models\Admin\EmailCampaign;
use App\Models\Admin\NotificationLog;
use App\Models\Admin\Student;
use App\Models\User;
use App\Services\EmailService;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Communication ▸ Sent Emails (permission emails.send): every send made from Send Email — who sent it, to whom
 * (filters), the wording and how many were sent / waiting / failed. Opening one (?campaign=id) lists every
 * receiver with the status of their email; failed ones can be sent again.
 * Super Admin sees all sends; Institute Admin the sends of their institute.
 */
class SentEmailsComponent extends Component
{
    use WithPagination, RecordsAuditTrail;

    protected $paginationTheme = 'bootstrap';

    public $campaign = '';
    public $search = '';
    public $filterAudience = '';
    public $recipientSearch = '';
    public $recipientStatus = '';

    protected $queryString = [
        'campaign' => ['except' => ''], 'search' => ['except' => ''], 'filterAudience' => ['except' => '', 'as' => 'audience'],
        'recipientStatus' => ['except' => '', 'as' => 'status'],
    ];

    public function mount()
    {
        $this->authorizeView();
    }

    public function hydrate()
    {
        $this->authorizeView();
    }

    protected function authorizeView()
    {
        abort_unless(Auth::user() && Auth::user()->can('emails.send'), 403);
    }

    public function updating($property)
    {
        if (in_array($property, ['search', 'filterAudience', 'recipientSearch', 'recipientStatus', 'campaign'], true)) {
            $this->resetPage();
        }
    }

    public function open($id)
    {
        $this->campaign = (string) (int) $id;
        $this->recipientSearch = $this->recipientStatus = '';
        $this->resetPage();
    }

    public function back()
    {
        $this->campaign = '';
        $this->resetPage();
    }

    protected function current(): ?EmailCampaign
    {
        return $this->campaign !== '' ? EmailCampaign::visibleTo(Auth::user())->with(['sender:id,name', 'institute:id,name'])->find((int) $this->campaign) : null;
    }

    /** Send every failed email of this send again (in the background). */
    public function retryFailed()
    {
        $campaign = $this->current();
        abort_unless($campaign, 404);

        $emails = app(EmailService::class);
        $logs = $campaign->logs()->where('status', 'failed')->whereNotNull('recipient')->orderBy('id')->limit(500)->get();
        $logs->each(fn ($log) => $emails->retry($log));

        if ($logs->isNotEmpty()) {
            $this->audit('update', 'Emails', $campaign, ['description' => 'Sent ' . $logs->count() . ' failed ' . Str::plural('email', $logs->count()) . ' of "' . Str::limit($campaign->subject, 80) . '" again']);
        }
        $this->dispatchBrowserEvent('show-toast', ['type' => $logs->isEmpty() ? 'info' : 'success',
            'message' => $logs->isEmpty() ? 'No failed emails to send again.' : $logs->count() . ' failed ' . Str::plural('email', $logs->count()) . ' queued again.']);
    }

    protected function campaigns()
    {
        $term = trim($this->search);

        return EmailCampaign::visibleTo(Auth::user())
            ->with(['sender:id,name', 'institute:id,name,code'])
            ->withCount([
                'logs as sent_count' => fn ($q) => $q->where('status', 'sent'),
                'logs as pending_count' => fn ($q) => $q->where('status', 'pending'),
                'logs as failed_count' => fn ($q) => $q->where('status', 'failed'),
            ])
            ->when($this->filterAudience, fn ($q) => $q->where('audience', $this->filterAudience))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('subject', 'like', "%{$term}%")->orWhere('filters_summary', 'like', "%{$term}%")))
            ->orderByDesc('id')
            ->paginate(20);
    }

    protected function recipients(EmailCampaign $campaign)
    {
        $term = trim($this->recipientSearch);

        return $campaign->logs()
            ->with('notifiable')
            ->when($this->recipientStatus, fn ($q) => $q->where('status', $this->recipientStatus))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('recipient', 'like', "%{$term}%")
                ->orWhereHasMorph('notifiable', [Student::class], fn ($s) => $s->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('er_number', 'like', "%{$term}%"))
                ->orWhereHasMorph('notifiable', [User::class], fn ($u) => $u->where('name', 'like', "%{$term}%"))))
            ->orderBy('id')
            ->paginate(25);
    }

    /** [name, sub line] of one receiver. */
    public static function who(NotificationLog $log): array
    {
        $payload = (array) $log->payload;
        $who = $log->notifiable;
        if ($who instanceof Student) {
            $isParent = mb_strtolower((string) $log->recipient) !== mb_strtolower((string) $who->email);

            return [$payload['to_name'] ?? $who->full_name, $isParent ? 'Parent of ' . $who->full_name : trim('Student · ' . ($who->er_number ?: $who->status_label))];
        }
        if ($who instanceof User) {
            return [$who->name, $who->role_display_name];
        }

        return [$payload['to_name'] ?? '—', '—'];
    }

    public function render()
    {
        $campaign = $this->current();
        if ($this->campaign !== '' && !$campaign) {
            $this->campaign = '';
        }

        $data = ['send' => $campaign, 'justSent' => (int) session('just_sent')];
        if ($campaign) {
            $data['counts'] = $campaign->statusCounts();
            $data['logs'] = $this->recipients($campaign);
            $data['previewHtml'] = optional($campaign->logs()->orderBy('id')->first())->payload['html'] ?? null;
        } else {
            $data['campaigns'] = $this->campaigns();
        }

        return view('livewire.admin.emails.sent-emails-component', $data)->layout('layouts.admin.master');
    }
}
