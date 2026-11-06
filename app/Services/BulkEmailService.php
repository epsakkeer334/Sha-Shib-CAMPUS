<?php

namespace App\Services;

use App\Models\Admin\Batch;
use App\Models\Admin\Course;
use App\Models\Admin\EmailCampaign;
use App\Models\Admin\Institute;
use App\Models\Admin\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Communication ▸ Send Email: one message to many students or staff.
 *
 *  - Students (permission emails.send): chosen one by one, or by institute / course / batch / status / unpaid fees;
 *    to the student, the parent or both. Institute users only ever reach their own institute's students.
 *  - Institute staff (Super Admin only): by institute and role, or chosen one by one.
 *
 * Each receiver gets an own copy with the placeholders filled in, through EmailService (same branded layout,
 * same Email & SMS Log, sent in the background). The send itself is kept in email_campaigns.
 *
 * Filters ($f): audience (students|staff), mode (group|selected), selected [ids], institutes [ids],
 * courses [ids], batches [ids], statuses [keys], fees ('' | unpaid), send_to (student|parent|both),
 * roles [names], active_only (bool).
 */
class BulkEmailService
{
    /** Most receivers in one send; more → send in parts (by batch or course). */
    const MAX_RECIPIENTS = 2000;

    const EVENT = 'bulk_email';
    const TEST_EVENT = 'bulk_email_test';

    public function __construct(protected EmailService $emails)
    {
    }

    // ------------------------------------------------------------------ Who receives it

    public function studentQuery(User $actor, array $f): Builder
    {
        $query = Student::query()->when(!$actor->isSuperAdmin(), fn ($q) => $q->where('institute_id', $actor->institute_id));

        if (($f['mode'] ?? 'group') === 'selected') {
            return $query->whereIn('id', array_map('intval', (array) ($f['selected'] ?? [])) ?: [0]);
        }

        return $query
            ->when($f['institutes'] ?? [], fn ($q, $ids) => $q->whereIn('institute_id', array_map('intval', $ids)))
            ->when($f['courses'] ?? [], fn ($q, $ids) => $q->whereIn('course_id', array_map('intval', $ids)))
            ->when($f['batches'] ?? [], fn ($q, $ids) => $q->whereIn('batch_id', array_map('intval', $ids)))
            ->when($f['statuses'] ?? [], fn ($q, $keys) => $q->whereIn('status', $keys))
            ->when(($f['fees'] ?? '') === 'unpaid', fn ($q) => $q->whereHas('dues', fn ($d) => $d->whereNotIn('status', ['cleared', 'waived'])));
    }

    /** Institute users (not students, not Super Admins). Super Admin only. */
    public function staffQuery(User $actor, array $f): Builder
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $query = User::query()->whereNotNull('institute_id')->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', ['student', 'super-admin']));

        if (($f['mode'] ?? 'group') === 'selected') {
            return $query->whereIn('id', array_map('intval', (array) ($f['selected'] ?? [])) ?: [0]);
        }

        return $query
            ->when($f['institutes'] ?? [], fn ($q, $ids) => $q->whereIn('institute_id', array_map('intval', $ids)))
            ->when($f['roles'] ?? [], fn ($q, $roles) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', $roles)))
            ->when($f['active_only'] ?? true, fn ($q) => $q->where('status', true));
    }

    public function query(User $actor, array $f): Builder
    {
        return ($f['audience'] ?? 'students') === 'staff' ? $this->staffQuery($actor, $f) : $this->studentQuery($actor, $f);
    }

    /**
     * The receivers: list of [model, email, name, kind (student|parent|staff), institute_id], one per address.
     *
     * @return array{list: Collection, matched: int, no_email: int, duplicate: int, too_many: bool}
     */
    public function recipients(User $actor, array $f): array
    {
        $query = $this->query($actor, $f);
        $matched = (clone $query)->count();
        $result = ['list' => collect(), 'matched' => $matched, 'no_email' => 0, 'duplicate' => 0, 'too_many' => $matched > self::MAX_RECIPIENTS];
        if ($matched === 0 || $result['too_many']) {
            return $result;
        }

        $staff = ($f['audience'] ?? 'students') === 'staff';
        $people = $staff
            ? $query->with('roles')->orderBy('name')->get()
            : $query->with(['course:id,name', 'batch:id,code,name'])->orderBy('first_name')->orderBy('last_name')->get();

        $sendTo = $f['send_to'] ?? 'student';
        $seen = [];
        $list = collect();
        $add = function ($model, $email, $name, $kind) use (&$seen, &$result, $list) {
            $email = trim((string) $email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['no_email']++;

                return;
            }
            if (isset($seen[mb_strtolower($email)])) {
                $result['duplicate']++;

                return;
            }
            $seen[mb_strtolower($email)] = true;
            $list->push(['model' => $model, 'email' => $email, 'name' => $name, 'kind' => $kind, 'institute_id' => $model->institute_id]);
        };

        foreach ($people as $person) {
            if ($staff) {
                $add($person, $person->email, $person->name, 'staff');
                continue;
            }
            if (in_array($sendTo, ['student', 'both'], true)) {
                $add($person, $person->email, $person->full_name, 'student');
            }
            if (in_array($sendTo, ['parent', 'both'], true)) {
                $add($person, $person->parent_email, $person->parent_name ?: 'Parent of ' . $person->full_name, 'parent');
            }
        }

        $result['list'] = $list;
        $result['too_many'] = $list->count() > self::MAX_RECIPIENTS;

        return $result;
    }

    // ------------------------------------------------------------------ Wording

    /** Ready-made wordings for the audience (Email Templates ▸ Announcements), switched-off ones left out. */
    public function templates(string $audience, ?int $instituteId): array
    {
        $want = $audience === 'staff' ? 'staff' : 'student';

        return collect($this->emails->catalogue())
            ->filter(fn ($t) => ($t['group'] ?? '') === 'announcements' && ($t['audience'] ?? '') === $want)
            ->map(fn ($t, $key) => ['key' => $key, 'name' => $t['name'], 'description' => $t['description']] + $this->emails->template($key, $instituteId))
            ->filter(fn ($t) => $t['status'])
            ->map(fn ($t) => collect($t)->except('model')->all())
            ->all();
    }

    /** Placeholders that can be used: [name => description]. */
    public function placeholders(string $audience): array
    {
        $extra = $audience === 'staff'
            ? config('email_templates.staff_placeholders', [])
            : config('email_templates.student_placeholders', []) + config('email_templates.bulk_student_placeholders', []);

        return $extra + config('email_templates.common_placeholders', []);
    }

    /** Placeholder values for one receiver. */
    public function varsFor(array $r, array $template): array
    {
        $model = $r['model'];
        if ($r['kind'] === 'staff') {
            return ['recipient_name' => $model->name, 'first_name' => Str::before($model->name, ' ') ?: $model->name, 'role' => $model->role_display_name];
        }

        $vars = $this->emails->studentVars($model) + [
            'batch' => $model->batch ? trim($model->batch->code . ' · ' . $model->batch->name, ' ·') : '',
            'parent_name' => (string) $model->parent_name,
        ];
        if (str_contains(implode(' ', [$template['subject'], $template['body'], $template['button_text'] ?? '', $template['button_url'] ?? '']), '{outstanding}')) {
            $vars['outstanding'] = money_inr($model->outstandingAmount());
        }
        if ($r['kind'] === 'parent') {
            $vars['recipient_name'] = $r['name'];
            $vars['first_name'] = Str::before($r['name'], ' ') ?: $r['name'];
        }

        return $vars;
    }

    public function render(array $r, array $template): array
    {
        return $this->emails->render($template, $this->varsFor($r, $template) + $this->emails->commonVars($r['institute_id']), $r['institute_id']);
    }

    /** Preview for the first receiver, or with example values when nobody matches yet. */
    public function preview(?array $first, array $template, string $audience, ?int $instituteId): array
    {
        if ($first) {
            return $this->render($first, $template);
        }

        $sample = $this->emails->sampleVars($audience === 'staff' ? 'announcement_staff' : 'announcement_students', $instituteId);

        return $this->emails->render($template, $sample, $instituteId);
    }

    // ------------------------------------------------------------------ Sending

    /**
     * Send to everyone matching $f. Returns the campaign; every email is in the log (sent in the background).
     */
    public function send(User $actor, array $f, array $template, ?string $templateKey = null): EmailCampaign
    {
        $recipients = $this->recipients($actor, $f);
        abort_if($recipients['too_many'], 422, 'Too many receivers.');
        abort_if($recipients['list']->isEmpty(), 422, 'Nobody to send to.');

        $institutes = $recipients['list']->pluck('institute_id')->unique()->values();

        return DB::transaction(function () use ($actor, $f, $template, $templateKey, $recipients, $institutes) {
            $campaign = EmailCampaign::create([
                'institute_id' => $actor->isSuperAdmin() ? ($institutes->count() === 1 ? $institutes->first() : null) : $actor->institute_id,
                'audience' => ($f['audience'] ?? 'students') === 'staff' ? 'staff' : 'students',
                'send_to' => ($f['audience'] ?? 'students') === 'staff' ? null : ($f['send_to'] ?? 'student'),
                'filters' => collect($f)->only(['mode', 'selected', 'institutes', 'courses', 'batches', 'statuses', 'fees', 'send_to', 'roles', 'active_only'])->all(),
                'filters_summary' => Str::limit($this->summary($f), 495),
                'template_key' => $templateKey,
                'subject' => $template['subject'],
                'body' => $template['body'],
                'button_text' => $template['button_text'] ?: null,
                'button_url' => $template['button_url'] ?: null,
                'recipients_total' => $recipients['list']->count(),
                'skipped_no_email' => $recipients['no_email'],
                'skipped_duplicate' => $recipients['duplicate'],
                'created_by' => $actor->id,
            ]);

            foreach ($recipients['list'] as $r) {
                $this->emails->sendCustom($r['model'], '', '', [
                    'email' => $r['email'],
                    'to_name' => $r['name'],
                    'institute_id' => $r['institute_id'],
                    'event_type' => self::EVENT,
                    'rendered' => $this->render($r, $template),
                    'log' => ['template_key' => $templateKey, 'email_campaign_id' => $campaign->id],
                ]);
            }

            return $campaign;
        });
    }

    /** One test copy (values of the first receiver) to $email; waits for SMTP2GO's answer. */
    public function sendTest(User $actor, array $f, array $template, string $email)
    {
        $first = $this->recipients($actor, $f)['list']->first();
        $audience = ($f['audience'] ?? 'students') === 'staff' ? 'staff' : 'students';
        $instituteId = $first['institute_id'] ?? ($actor->isSuperAdmin() ? null : $actor->institute_id);
        $rendered = $this->preview($first, $template, $audience, $instituteId);

        return $this->emails->sendCustom($actor, '[Test] ' . $rendered['subject'], '', [
            'email' => $email, 'institute_id' => $instituteId, 'event_type' => self::TEST_EVENT, 'rendered' => $rendered, 'now' => true,
        ]);
    }

    /** "Students · AME B1.1 · Batch SHA-B11-2026 · Active · to student and parent" */
    public function summary(array $f): string
    {
        $staff = ($f['audience'] ?? 'students') === 'staff';
        $parts = [$staff ? 'Institute staff' : 'Students'];

        if (($f['mode'] ?? 'group') === 'selected') {
            $parts[] = count((array) ($f['selected'] ?? [])) . ' chosen one by one';
        } else {
            $names = fn ($model, $ids, $col = 'name') => $model::query()->withoutGlobalScopes()->whereIn('id', $ids)->orderBy($col)->pluck($col)->implode(', ');
            $parts[] = !empty($f['institutes']) ? $names(Institute::class, $f['institutes']) : 'all institutes';
            if (!$staff) {
                if (!empty($f['courses'])) {
                    $parts[] = $names(Course::class, $f['courses']);
                }
                if (!empty($f['batches'])) {
                    $parts[] = 'Batch ' . $names(Batch::class, $f['batches'], 'code');
                }
                if (!empty($f['statuses'])) {
                    $parts[] = collect($f['statuses'])->map(fn ($s) => config("camp.student_statuses.{$s}.0", $s))->implode(', ');
                }
                if (($f['fees'] ?? '') === 'unpaid') {
                    $parts[] = 'with unpaid fees';
                }
            } else {
                $parts[] = !empty($f['roles']) ? collect($f['roles'])->map(fn ($r) => config("camp.roles.{$r}", $r))->implode(', ') : 'all roles';
                if ($f['active_only'] ?? true) {
                    $parts[] = 'active users';
                }
            }
        }

        if (!$staff) {
            $parts[] = 'to ' . mb_strtolower(EmailCampaign::SEND_TO[$f['send_to'] ?? 'student'] ?? 'student');
        }

        return implode(' · ', $parts);
    }
}
