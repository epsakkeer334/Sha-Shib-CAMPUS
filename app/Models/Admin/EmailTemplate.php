<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Saved wording of one email (key from config/email_templates.php). institute_id null = group-wide
 * version (Super Admin); otherwise that institute's own version. No row = the built-in default.
 * Not institute-scoped globally: group rows have no institute — callers filter (see EmailService::template()).
 */
class EmailTemplate extends Model
{
    protected $fillable = ['institute_id', 'key', 'subject', 'body', 'button_text', 'button_url', 'status', 'created_by', 'updated_by'];

    protected $casts = ['status' => 'boolean'];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForScope($query, ?int $instituteId)
    {
        return $instituteId ? $query->where('institute_id', $instituteId) : $query->whereNull('institute_id');
    }
}
