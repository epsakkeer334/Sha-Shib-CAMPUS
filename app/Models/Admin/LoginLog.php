<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One sign-in, failed / blocked attempt or sign-out (Monitoring ▸ Login Activity). Written by App\Services\LoginLogger.
 */
class LoginLog extends Model
{
    use MassPrunable;

    const UPDATED_AT = null;

    /** Rows older than this are removed by the daily `model:prune` (app/Console/Kernel.php). */
    const KEEP_DAYS = 365;

    const PANELS = ['admin' => ['Admin panel', 'ti ti-layout-dashboard', 'blue'], 'portal' => ['Student portal', 'ti ti-school', 'teal']];

    const EVENTS = [
        'login' => ['Signed in', 'ti ti-login', 'green'],
        'failed' => ['Failed', 'ti ti-alert-triangle', 'red'],
        'blocked' => ['Blocked', 'ti ti-lock', 'amber'],
        'logout' => ['Signed out', 'ti ti-logout', 'slate'],
    ];

    protected $fillable = ['user_id', 'institute_id', 'email', 'panel', 'event', 'reason', 'ip', 'user_agent', 'browser', 'platform', 'device', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
