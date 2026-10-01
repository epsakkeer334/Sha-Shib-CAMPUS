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
        'recipient',
        'payload',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
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
