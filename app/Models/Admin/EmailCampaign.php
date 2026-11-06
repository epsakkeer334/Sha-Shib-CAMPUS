<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One email sent to many people from Communication ▸ Send Email (App\Services\BulkEmailService).
 * Every email of the send is a notifications_log row with email_campaign_id; sent / failed / pending
 * counts are read from there, so they follow the background sending.
 */
class EmailCampaign extends Model
{
    const AUDIENCES = ['students' => ['Students', 'ti ti-school'], 'staff' => ['Institute staff', 'ti ti-users']];
    const SEND_TO = ['student' => 'Student', 'parent' => 'Parent / guardian', 'both' => 'Student and parent'];

    protected $fillable = [
        'institute_id', 'audience', 'send_to', 'filters', 'filters_summary', 'template_key',
        'subject', 'body', 'button_text', 'button_url',
        'recipients_total', 'skipped_no_email', 'skipped_duplicate', 'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'recipients_total' => 'integer',
        'skipped_no_email' => 'integer',
        'skipped_duplicate' => 'integer',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * Super Admin sees every send; others the sends of their own institute.
     */
    public function scopeVisibleTo($query, $user)
    {
        if ($user && !$user->isSuperAdmin()) {
            $query->where('institute_id', $user->institute_id);
        }

        return $query;
    }

    /** [sent, pending, failed] from the log. */
    public function statusCounts(): array
    {
        $counts = $this->logs()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['sent' => (int) ($counts['sent'] ?? 0), 'pending' => (int) ($counts['pending'] ?? 0), 'failed' => (int) ($counts['failed'] ?? 0)];
    }
}
