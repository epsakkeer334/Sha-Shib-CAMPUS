<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected $fillable = [
        'action',
        'module',
        'model_id',
        'model_type',
        'old_values',
        'new_values',
        'reason',
        'description',
        'user_id',
        'institute_id',
        'user_ip',
        'performed_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'performed_at' => 'datetime',
    ];

    protected $appends = [
        'formatted_performed_at',
        'action_badge',
        'user_name',
        'module_name'
    ];

    /**
     * Get the user who performed the activity
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related model
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get user name from relationship
     */
    public function getUserNameAttribute()
    {
        return $this->user ? $this->user->name : 'System';
    }

    /**
     * Get formatted module name
     */
    public function getModuleNameAttribute()
    {
        $modules = [
            'users' => 'Users',
            'institutes' => 'Institutes',
            'courses' => 'Courses',
            'curriculums' => 'Curriculums',
            'subjects' => 'Subjects',
            'roles' => 'Roles',
            'permissions' => 'Permissions',
            'permission_groups' => 'Permission Groups',
        ];

        return $modules[$this->module] ?? ucfirst($this->module);
    }

    /**
     * Get formatted performed at
     */
    public function getFormattedPerformedAtAttribute()
    {
        return $this->performed_at ? $this->performed_at->format('d M Y, h:i A') : '-';
    }

    /**
     * Get action badge HTML
     */
    public function getActionBadgeAttribute()
    {
        $badges = [
            'create' => ['class' => 'bg-success', 'icon' => 'plus'],
            'update' => ['class' => 'bg-info', 'icon' => 'edit'],
            'delete' => ['class' => 'bg-danger', 'icon' => 'trash'],
            'approve' => ['class' => 'bg-primary', 'icon' => 'check'],
            'reject' => ['class' => 'bg-warning', 'icon' => 'x'],
            'override' => ['class' => 'bg-secondary', 'icon' => 'refresh'],
            'login' => ['class' => 'bg-dark', 'icon' => 'login'],
            'logout' => ['class' => 'bg-dark', 'icon' => 'logout'],
            'upload' => ['class' => 'bg-purple', 'icon' => 'upload'],
            'download' => ['class' => 'bg-teal', 'icon' => 'download'],
            'restore' => ['class' => 'bg-success', 'icon' => 'restore'],
            'reset_password' => ['class' => 'bg-warning', 'icon' => 'refresh'],
            'change_password' => ['class' => 'bg-primary', 'icon' => 'key'],
        ];

        $badge = $badges[$this->action] ?? ['class' => 'bg-secondary', 'icon' => 'activity'];
        $class = $badge['class'];
        $textColor = str_replace('bg-', '', $class);

        // Handle custom colors
        $customColors = ['bg-purple' => 'purple', 'bg-teal' => 'teal'];
        if (isset($customColors[$class])) {
            $textColor = $customColors[$class];
        }

        return "<span class='badge {$class} bg-opacity-10 text-{$textColor} px-3 py-1 rounded-pill'>
            <i class='ti ti-{$badge['icon']} me-1'></i>
            " . ucfirst(str_replace('_', ' ', $this->action)) . "
        </span>";
    }

    /**
     * Scope to filter by module
     */
    public function scopeModule($query, $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope to filter by action
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('performed_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
