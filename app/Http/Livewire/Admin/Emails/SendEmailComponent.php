<?php

namespace App\Http\Livewire\Admin\Emails;

use App\Models\Admin\Batch;
use App\Models\Admin\Course;
use App\Models\Admin\EmailCampaign;
use App\Models\Admin\Institute;
use App\Models\Admin\InstituteCourse;
use App\Models\Admin\Student;
use App\Models\User;
use App\Services\BulkEmailService;
use App\Services\EmailService;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Communication ▸ Send Email (permission emails.send).
 *  - Students: chosen one by one, or by institute / course / batch / status / unpaid fees; to the student, parent or both.
 *    Institute Admin: own institute only.
 *  - Institute staff (Super Admin only): by institute and role, or chosen one by one.
 * Wording from a ready-made template (Email Templates ▸ Announcements) or written here; live preview with the
 * first receiver's details, test copy, then send. Every email goes to the Email & SMS Log, the send to Sent Emails.
 */
class SendEmailComponent extends Component
{
    use RecordsAuditTrail;

    // Who
    public $audience = 'students';   // students | staff
    public $mode = 'group';          // group | selected
    public $institutes = [];
    public $courses = [];
    public $batches = [];
    public $statuses = [];
    public $fees = '';               // '' | unpaid
    public $sendTo = 'student';      // student | parent | both
    public $roles = [];
    public $activeOnly = true;
    public $selected = [];
    public $search = '';
    public $receiversOpen = true;    // UI only: "Who receives it" expanded or folded to a summary

    // What
    public $templateKey = '';
    public $subject = '', $body = '', $buttonText = '', $buttonUrl = '';
    public $dirty = false;

    public $testEmail = '';
    public ?array $testResult = null;
    public $confirming = false;

    /** Receivers of the current filters: counts, first few, first receiver (for the preview). */
    public array $info = [];

    const FILTER_PROPS = ['audience', 'mode', 'institutes', 'courses', 'batches', 'statuses', 'fees', 'sendTo', 'roles', 'activeOnly', 'selected'];

    public function mount()
    {
        $this->authorizeSend();
        $this->testEmail = (string) Auth::user()->email;

        if ($this->copyFromQuery()) {
            $this->guardScope();
            $this->refreshInfo();

            return;
        }
        if ($this->selectedFromQuery()) {
            $this->mode = 'selected';
        }
        $this->applyFirstTemplate();
        $this->refreshInfo();
    }

    /** ?from=<send id> (Sent Emails ▸ Use again): same receivers and wording, to change and send again. */
    protected function copyFromQuery(): bool
    {
        $from = (int) request()->query('from', 0);
        $campaign = $from ? EmailCampaign::visibleTo(Auth::user())->find($from) : null;
        if (!$campaign || ($campaign->audience === 'staff' && !$this->isSuperAdmin())) {
            return false;
        }

        $f = (array) $campaign->filters;
        $this->audience = $campaign->audience;
        $this->mode = $f['mode'] ?? 'group';
        $this->selected = (array) ($f['selected'] ?? []);
        $this->institutes = (array) ($f['institutes'] ?? []);
        $this->courses = (array) ($f['courses'] ?? []);
        $this->batches = (array) ($f['batches'] ?? []);
        $this->statuses = (array) ($f['statuses'] ?? []);
        $this->fees = (string) ($f['fees'] ?? '');
        $this->sendTo = $campaign->send_to ?: 'student';
        $this->roles = (array) ($f['roles'] ?? []);
        $this->activeOnly = (bool) ($f['active_only'] ?? true);
        $this->templateKey = (string) $campaign->template_key;
        $this->subject = $campaign->subject;
        $this->body = $campaign->body;
        $this->buttonText = (string) $campaign->button_text;
        $this->buttonUrl = (string) $campaign->button_url;
        $this->dirty = true;

        return true;
    }

    public function hydrate()
    {
        $this->authorizeSend();
        $this->guardScope();
    }

    protected function authorizeSend()
    {
        abort_unless(Auth::user() && Auth::user()->can('emails.send'), 403);
    }

    protected function isSuperAdmin(): bool
    {
        return Auth::user()->isSuperAdmin();
    }

    /** Institute users: students of their own institute only, whatever the browser sends. */
    protected function guardScope(): void
    {
        if (!$this->isSuperAdmin()) {
            $this->audience = 'students';
            $this->institutes = [];
            $this->roles = [];
        }
        $this->audience = $this->audience === 'staff' ? 'staff' : 'students';
        $this->mode = $this->mode === 'selected' ? 'selected' : 'group';
        $this->sendTo = array_key_exists($this->sendTo, EmailCampaign::SEND_TO) ? $this->sendTo : 'student';
        foreach (['institutes', 'courses', 'batches', 'selected'] as $prop) {
            $this->{$prop} = array_values(array_unique(array_map('intval', array_filter((array) $this->{$prop}))));
        }
        $this->statuses = array_values(array_intersect((array) $this->statuses, array_keys(config('camp.student_statuses'))));
        $this->roles = array_values(array_intersect((array) $this->roles, array_keys($this->roleOptions())));
    }

    /** ?students=1,2,3 from the students list. */
    protected function selectedFromQuery(): bool
    {
        $ids = collect(explode(',', (string) request()->query('students', '')))->map(fn ($v) => (int) $v)->filter()->unique();
        if ($ids->isEmpty()) {
            return false;
        }
        $this->selected = Student::query()->when(!$this->isSuperAdmin(), fn ($q) => $q->where('institute_id', Auth::user()->institute_id))
            ->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return (bool) $this->selected;
    }

    protected function bulk(): BulkEmailService
    {
        return app(BulkEmailService::class);
    }

    public function filters(): array
    {
        return [
            'audience' => $this->audience, 'mode' => $this->mode, 'selected' => $this->selected,
            'institutes' => $this->institutes, 'courses' => $this->courses, 'batches' => $this->batches,
            'statuses' => $this->statuses, 'fees' => $this->fees, 'send_to' => $this->sendTo,
            'roles' => $this->roles, 'active_only' => (bool) $this->activeOnly,
        ];
    }

    // ------------------------------------------------------------------ Filters

    public function updated($property)
    {
        $root = Str::before($property, '.');
        if (in_array($root, ['subject', 'body', 'buttonText', 'buttonUrl'], true)) {
            $this->dirty = true;
            $this->confirming = false;

            return;
        }
        if (!in_array($root, self::FILTER_PROPS, true)) {
            return;
        }

        $this->guardScope();
        if ($root === 'audience') {
            $this->mode = 'group';
            $this->selected = [];
            $this->search = '';
            if ($this->dirty) {
                $this->templateKey = '';
            } else {
                $this->applyFirstTemplate();
            }
        }
        if (in_array($root, ['audience', 'institutes'], true)) {
            $this->courses = array_values(array_intersect($this->courses, $this->courseOptions()->pluck('id')->all()));
        }
        if (in_array($root, ['audience', 'institutes', 'courses'], true)) {
            $this->batches = array_values(array_intersect($this->batches, $this->batchOptions()->pluck('id')->all()));
        }

        $this->confirming = false;
        $this->testResult = null;
        $this->refreshInfo();
    }

    public function toggle($property, $value)
    {
        if (!in_array($property, ['institutes', 'courses', 'batches', 'statuses', 'roles'], true)) {
            return;
        }
        $list = (array) $this->{$property};
        $value = in_array($property, ['statuses', 'roles'], true) ? (string) $value : (int) $value;
        $this->{$property} = in_array($value, $list, false) ? array_values(array_diff($list, [$value])) : array_merge($list, [$value]);
        $this->updated($property);
    }

    public function clear($property)
    {
        if (in_array($property, ['institutes', 'courses', 'batches', 'statuses', 'roles', 'selected'], true)) {
            $this->{$property} = [];
            $this->updated($property);
        }
    }

    public function pick($id)
    {
        $id = (int) $id;
        if ($id && !in_array($id, $this->selected, true) && $this->bulk()->query(Auth::user(), ['mode' => 'selected', 'selected' => [$id]] + $this->filters())->exists()) {
            $this->selected[] = $id;
        }
        $this->search = '';
        $this->updated('selected');
    }

    public function unpick($id)
    {
        $this->selected = array_values(array_diff($this->selected, [(int) $id]));
        $this->updated('selected');
    }

    protected function refreshInfo(): void
    {
        $r = $this->bulk()->recipients(Auth::user(), $this->filters());
        $first = $r['list']->first();

        $this->info = [
            'matched' => $r['matched'],
            'count' => $r['list']->count(),
            'no_email' => $r['no_email'],
            'duplicate' => $r['duplicate'],
            'too_many' => $r['too_many'],
            'institutes' => $r['list']->pluck('institute_id')->unique()->count(),
            'sample' => $r['list']->take(6)->map(fn ($x) => [
                'name' => $x['name'], 'email' => $x['email'], 'kind' => $x['kind'],
                'sub' => $x['kind'] === 'staff' ? $x['model']->role_display_name : ($x['model']->er_number ?: optional($x['model']->course)->display_name),
            ])->values()->all(),
            'first' => $first ? ['id' => $first['model']->getKey(), 'kind' => $first['kind'], 'email' => $first['email'], 'name' => $first['name']] : null,
        ];
    }

    /** The first receiver again (for the preview), from the ids kept in $info. */
    protected function firstRecipient(): ?array
    {
        $first = $this->info['first'] ?? null;
        if (!$first) {
            return null;
        }
        $model = $this->audience === 'staff'
            ? User::with('roles')->find($first['id'])
            : Student::with(['course:id,name', 'batch:id,code,name'])->find($first['id']);

        return $model ? ['model' => $model, 'email' => $first['email'], 'name' => $first['name'], 'kind' => $first['kind'], 'institute_id' => $model->institute_id] : null;
    }

    // ------------------------------------------------------------------ Wording

    protected function wordingInstitute(): ?int
    {
        if (!$this->isSuperAdmin()) {
            return (int) Auth::user()->institute_id;
        }

        return count($this->institutes) === 1 ? (int) $this->institutes[0] : null;
    }

    protected function applyFirstTemplate(): void
    {
        $first = array_key_first($this->bulk()->templates($this->audience, $this->wordingInstitute()));
        $first ? $this->useTemplate($first) : $this->templateKey = '';
    }

    public function useTemplate($key)
    {
        $this->confirming = false;
        if ($key === '' || $key === null) {
            $this->templateKey = '';

            return;
        }
        $templates = $this->bulk()->templates($this->audience, $this->wordingInstitute());
        if (!isset($templates[$key])) {
            return;
        }
        $t = $templates[$key];
        $this->templateKey = $key;
        $this->subject = $t['subject'];
        $this->body = $t['body'];
        $this->buttonText = (string) $t['button_text'];
        $this->buttonUrl = (string) $t['button_url'];
        $this->dirty = false;
        $this->resetValidation();
    }

    public function blank()
    {
        $this->templateKey = '';
        $this->subject = $this->body = $this->buttonText = $this->buttonUrl = '';
        $this->dirty = false;
        $this->confirming = false;
        $this->resetValidation();
    }

    protected function template(): array
    {
        return ['subject' => trim((string) $this->subject), 'body' => rtrim(str_replace("\r\n", "\n", (string) $this->body)),
            'button_text' => trim((string) $this->buttonText), 'button_url' => trim((string) $this->buttonUrl)];
    }

    protected function rules()
    {
        return [
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:5000',
            'buttonText' => 'nullable|required_with:buttonUrl|string|max:60',
            'buttonUrl' => ['nullable', 'required_with:buttonText', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (trim((string) $value) !== '' && !preg_match('#^(https?://|\{[a-z_]+\})#i', trim($value))) {
                    $fail('The button link must start with https:// or a placeholder such as {portal_url}.');
                }
            }],
        ];
    }

    protected $validationAttributes = ['buttonText' => 'button text', 'buttonUrl' => 'button link'];

    // ------------------------------------------------------------------ Send

    public function sendTest()
    {
        $this->validate(['testEmail' => 'required|email'] + $this->rules(), [], ['testEmail' => 'test address'] + $this->validationAttributes);
        $log = $this->bulk()->sendTest(Auth::user(), $this->filters(), $this->template(), $this->testEmail);

        $this->testResult = $log->status === 'sent'
            ? ['ok' => true, 'message' => "Test sent to {$this->testEmail}" . ($this->info['first'] ? " with {$this->info['first']['name']}'s details." : ' with example values.')]
            : ['ok' => false, 'message' => $log->error ?: 'SMTP2GO did not accept the email.'];
    }

    /** Step 1 of sending: check everything and show "Send to N people?". */
    public function review()
    {
        $this->validate();
        $this->refreshInfo();

        if ($this->info['too_many']) {
            $this->addError('audience', 'More than ' . number_format(BulkEmailService::MAX_RECIPIENTS) . ' receivers. Narrow the filters (for example by course or batch) and send in parts.');

            return;
        }
        if (!$this->info['count']) {
            $this->addError('audience', $this->mode === 'selected' ? 'Choose at least one person with an email address.' : 'Nobody with an email address matches these filters.');

            return;
        }

        $this->confirming = true;
    }

    public function send()
    {
        $this->validate();
        $this->refreshInfo();
        if (!$this->confirming || $this->info['too_many'] || !$this->info['count']) {
            $this->confirming = false;

            return $this->review();
        }

        $campaign = $this->bulk()->send(Auth::user(), $this->filters(), $this->template(), $this->templateKey ?: null);

        $this->audit('create', 'Emails', $campaign, [
            'new' => $campaign->only(['audience', 'send_to', 'filters_summary', 'subject', 'recipients_total', 'skipped_no_email', 'skipped_duplicate']),
            'description' => 'Sent email "' . Str::limit($campaign->subject, 80) . '" to ' . $campaign->recipients_total . ' ' . Str::plural('receiver', $campaign->recipients_total) . ' (' . Str::limit($campaign->filters_summary, 200) . ')',
        ]);

        session()->flash('just_sent', $campaign->id);

        return redirect()->route('admin.emails.sent', ['campaign' => $campaign->id]);
    }

    // ------------------------------------------------------------------ Options

    protected function instituteOptions()
    {
        return $this->isSuperAdmin() ? Institute::orderBy('name')->get(['id', 'name', 'code']) : collect();
    }

    protected function courseOptions()
    {
        $ids = InstituteCourse::visibleTo(Auth::user())->when($this->institutes, fn ($q) => $q->whereIn('institute_id', $this->institutes))->pluck('course_id')->unique();

        return Course::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'code']);
    }

    protected function batchOptions()
    {
        return Batch::query()
            ->when($this->institutes, fn ($q) => $q->whereIn('institute_id', $this->institutes))
            ->when($this->courses, fn ($q) => $q->whereIn('course_id', $this->courses))
            ->with('institute:id,code')
            ->orderByDesc('status')->orderByDesc('start_date')->limit(300)
            ->get(['id', 'institute_id', 'course_id', 'name', 'code', 'status']);
    }

    protected function roleOptions(): array
    {
        return collect(config('camp.roles'))->except(['super-admin', 'student'])->all();
    }

    protected function searchResults()
    {
        $term = trim((string) $this->search);
        if ($this->mode !== 'selected' || mb_strlen($term) < 2) {
            return collect();
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $actor = Auth::user();

        if ($this->audience === 'staff') {
            return $this->bulk()->staffQuery($actor, ['mode' => 'group', 'active_only' => false])
                ->whereNotIn('id', $this->selected ?: [0])
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('employee_code', 'like', $like))
                ->with(['roles', 'institute:id,name,code'])->orderBy('name')->limit(8)->get()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'sub' => $u->role_display_name . ' · ' . (optional($u->institute)->code ?? '')]);
        }

        return $this->bulk()->studentQuery($actor, ['mode' => 'group'])
            ->whereNotIn('id', $this->selected ?: [0])
            ->where(fn ($q) => $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                ->orWhere('er_number', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
            ->with(['course:id,name', 'institute:id,code'])->orderBy('first_name')->limit(8)->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name, 'email' => $s->email, 'sub' => trim(($s->er_number ?: $s->status_label) . ' · ' . optional($s->course)->name . ($this->isSuperAdmin() ? ' · ' . optional($s->institute)->code : ''), ' ·')]);
    }

    protected function selectedPeople()
    {
        if (!$this->selected) {
            return collect();
        }
        if ($this->audience === 'staff') {
            return User::with('roles')->whereIn('id', $this->selected)->orderBy('name')->get()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
        }

        return $this->bulk()->studentQuery(Auth::user(), ['mode' => 'selected', 'selected' => $this->selected])->orderBy('first_name')->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name, 'email' => $this->sendTo === 'parent' ? $s->parent_email : $s->email]);
    }

    public function render()
    {
        $emails = app(EmailService::class);
        $placeholders = $this->bulk()->placeholders($this->audience);

        $previewHtml = $previewSubject = $previewFrom = $previewReplyTo = null;
        try {
            $first = $this->firstRecipient();
            $rendered = $this->bulk()->preview($first, $this->template(), $this->audience, $first['institute_id'] ?? $this->wordingInstitute());
            $previewHtml = $rendered['html'];
            $previewSubject = $rendered['subject'];
            $previewFrom = $rendered['sender_name'];
            $previewReplyTo = $rendered['reply_to'];
        } catch (\Throwable $e) {
            report($e);
        }
        preg_match_all('/\{([a-z0-9_]+)\}/i', $this->subject . ' ' . $this->body . ' ' . $this->buttonText . ' ' . $this->buttonUrl, $m);

        return view('livewire.admin.emails.send-email-component', [
            'isSuperAdmin' => $this->isSuperAdmin(),
            'instituteName' => optional(Auth::user()->institute)->name,
            'instituteOptions' => $this->instituteOptions(),
            'courseOptions' => $this->courseOptions(),
            'batchOptions' => $this->batchOptions(),
            'statusOptions' => config('camp.student_statuses'),
            'roleOptions' => $this->roleOptions(),
            'searchResults' => $this->searchResults(),
            'selectedPeople' => $this->selectedPeople(),
            'templates' => $this->bulk()->templates($this->audience, $this->wordingInstitute()),
            'placeholders' => $placeholders,
            'unknown' => array_values(array_diff(array_unique($m[1]), array_keys($placeholders))),
            'previewHtml' => $previewHtml,
            'previewSubject' => $previewSubject,
            'previewFrom' => $previewFrom,
            'previewReplyTo' => $previewReplyTo,
            'senderEmail' => (string) config('services.smtp2go.sender_email'),
            'emailLive' => $emails->enabled(),
            'max' => BulkEmailService::MAX_RECIPIENTS,
            'workerMode' => \App\Support\BackgroundJobs::mode(),
        ])->layout('layouts.admin.master');
    }
}
