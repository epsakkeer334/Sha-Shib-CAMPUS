<?php

namespace App\Services;

use App\Models\Admin\Student;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * In-app workflow notifications (admin bell / portal bell). Who is told about what lives in
 * config/workflow_notifications.php:
 *   - staff: active users of the student's institute holding one of the event's permissions
 *     (+ Super Admins), never student accounts;
 *   - student: the student's portal login.
 * The user who performed the action is never notified about it. A failure here is reported but
 * never breaks the workflow step that raised it.
 */
class AppNotifier
{
    /**
     * @param  string|null  $only  'staff' or 'student' to limit the audience of this occurrence
     */
    public function notify(string $event, Student $student, array $vars = [], ?string $only = null): void
    {
        try {
            $config = config("workflow_notifications.events.{$event}");
            if (!$config) {
                return;
            }

            $actor = Auth::user();
            $vars += [
                'student' => $student->full_name,
                'er' => $student->er_number,
                'actor' => optional($actor)->name,
            ];

            if (!empty($config['staff']) && $only !== 'student') {
                $staff = $this->staffRecipients($student, $config['staff'])->reject(fn ($u) => $actor && $u->id === $actor->id);
                if ($staff->isNotEmpty()) {
                    $data = $this->payload($event, $config, $student, $vars, 'staff');
                    $staff->each(fn ($user) => $this->deliver($user, $data));
                }
            }

            if (!empty($config['student']) && $only !== 'staff' && $student->user_id && (!$actor || $actor->id !== $student->user_id)) {
                $user = User::where('status', true)->find($student->user_id);
                if ($user) {
                    $this->deliver($user, $this->payload($event, $config, $student, $vars, 'student'));
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Time-ordered UUID per notification, so lists sort correctly even within the same second.
     */
    protected function deliver(User $user, array $data): void
    {
        $notification = new WorkflowNotification($data);
        $notification->id = (string) Str::orderedUuid();
        $user->notify($notification);
    }

    /**
     * Active staff of the student's institute with any of the permissions, plus Super Admins.
     */
    public function staffRecipients(Student $student, array $permissions): Collection
    {
        $users = User::permission($permissions)
            ->where('institute_id', $student->institute_id)
            ->where('status', true)
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'student'))
            ->get();

        if (config('workflow_notifications.include_super_admin', true)) {
            $users = $users->merge(User::role('super-admin')->where('status', true)->get());
        }

        return $users->unique('id')->values();
    }

    protected function payload(string $event, array $config, Student $student, array $vars, string $audience): array
    {
        $template = $audience === 'student' ? ($config['student_message'] ?? $config['message']) : $config['message'];
        [$stageLabel] = config("workflow_notifications.stages.{$config['stage']}", [Str::headline($config['stage'])]);

        return [
            'event' => $event,
            'audience' => $audience,
            'title' => $this->fill($config['title'], $vars),
            'message' => $this->fill($template, $vars),
            'stage' => $config['stage'],
            'stage_label' => $stageLabel,
            'icon' => $config['icon'],
            'tone' => $config['tone'],
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'er_number' => $student->er_number,
            'institute_id' => $student->institute_id,
            'institute_code' => optional($student->institute)->code,
            'actor_name' => $vars['actor'],
            'url' => $this->url($event, $config['stage'], $student, $audience),
        ];
    }

    protected function fill(string $text, array $vars): string
    {
        // Longest keys first so ":documents" is not eaten by ":document"
        uksort($vars, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($vars as $key => $value) {
            $text = str_replace(':' . $key, (string) ($value ?? '—'), $text);
        }

        return $text;
    }

    /**
     * Where clicking the notification goes (relative URL).
     */
    protected function url(string $event, string $stage, Student $student, string $audience): string
    {
        if ($audience === 'student') {
            return match ($stage) {
                'documents', 'gate1' => route('portal.documents', [], false),
                'payment', 'gate2' => route('portal.payment', [], false),
                default => route('portal.status', [], false),
            };
        }

        return match ($event) {
            'registration_started' => route('admin.students.edit', $student->id, false),
            'gate1_approved', 'gate2_reopened' => route('admin.onboarding.payments', ['tab' => 'gate'], false),
            'payment_submitted' => route('admin.onboarding.payments', ['tab' => 'pending'], false),
            'er_issued' => route('admin.onboarding.enrollment.student', $student->id, false),
            'id_card_issued' => route('admin.students.enrollment', $student->id, false),
            default => route('admin.onboarding.documents', ['student' => $student->id], false),
        };
    }
}
