<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $table = 'notifications_log';

    protected $fillable = [
        'institute_id',
        'notifiable_type',
        'notifiable_id',
        'channel',
        'event_type',
        'template_key',
        'email_template_id',
        'email_campaign_id',
        'recipient',
        'subject',
        'payload',
        'status',
        'provider',
        'provider_message_id',
        'attempts',
        'last_attempt_at',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'attempts' => 'integer',
    ];

    protected $appends = ['status_html', 'formatted_created_at'];

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    /** The Send Email send this email belongs to, if any. */
    public function campaign()
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    /**
     * Rows visible to the user in lists: Super Admin sees all, others only their institute.
     */
    public function scopeVisibleTo($query, $user)
    {
        if ($user && !$user->isSuperAdmin()) {
            $query->where('institute_id', $user->institute_id);
        }

        return $query;
    }

    public function getStatusHtmlAttribute()
    {
        $class = ['sent' => 'success', 'failed' => 'danger'][$this->status] ?? 'warning';

        return "<span class='badge badge-soft-{$class}'>" . ucfirst($this->status) . '</span>';
    }

    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at ? $this->created_at->format('d M Y, h:i A') : '-';
    }
}
