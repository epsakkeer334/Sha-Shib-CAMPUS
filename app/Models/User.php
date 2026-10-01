<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Admin\Institute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Admin\AuditTrail;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'status',
        'user_image',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'status' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    protected $appends = [
        'user_image_url',
        'formatted_last_login',
        'is_online',
        'role_html',
        'status_html',
        'status_text'
    ];

    /**
     * Get the profile photo URL.
     */
    public function getUserImageUrlAttribute()
    {
        if ($this->user_image) {
            return asset('storage/users/' . $this->user_image);
        }
        return asset('admin/assets/img/default-avatar.png');
    }

    /**
     * Get the user's role display name.
     */
    public function getRoleDisplayNameAttribute()
    {
        $roles = $this->getRoleNames();
        if ($roles->isEmpty()) {
            return 'No Role';
        }

        $role = $roles->first();
        return match($role) {
            'super-admin' => 'Super Admin',
            'institute-admin' => 'Institute Admin',
            'accounts' => 'Accounts',
            'training-manager' => 'Training Manager',
            'hot' => 'HOT',
            'bic' => 'BIC',
            'faculty' => 'Faculty',
            'student' => 'Student',
            default => ucfirst(str_replace('-', ' ', $role))
        };
    }

    /**
     * Get the user's role badge class.
     */
    public function getRoleBadgeClassAttribute()
    {
        $roles = $this->getRoleNames();
        if ($roles->isEmpty()) {
            return 'bg-secondary';
        }

        $role = $roles->first();
        return match($role) {
            'super-admin' => 'bg-danger',
            'institute-admin' => 'bg-secondary',
            'accounts' => 'bg-success',
            'training-manager' => 'bg-info',
            'hot' => 'bg-warning',
            'bic' => 'bg-secondary',
            'faculty' => 'bg-dark',
            'student' => 'bg-teal',
            default => 'bg-secondary'
        };
    }

    /**
     * Get the user's role HTML (returns HTML badge with appropriate colors)
     */
    public function getRoleHtmlAttribute()
    {
        $roleName = $this->role_display_name;
        $badgeClass = $this->role_badge_class;

        // Determine text color based on badge class
        $textColor = str_replace('bg-', '', $badgeClass);

        // Map custom colors
        $customColors = [
            'bg-purple' => 'purple',
            'bg-teal' => 'teal',
        ];

        if (isset($customColors[$badgeClass])) {
            $textColor = $customColors[$badgeClass];
        }

        // Return HTML badge
        return '<span class="badge ' . $badgeClass . ' bg-opacity-10 text-' . $textColor . ' px-3 py-1 rounded-pill">
            <i class="ti ti-user-check me-1"></i> ' . $roleName . '
        </span>';
    }



    /**
     * Get formatted last login.
     */
    public function getFormattedLastLoginAttribute()
    {
        if (!$this->last_login_at) {
            return 'Never logged in';
        }

        return $this->last_login_at->diffForHumans() . ' (' .
               $this->last_login_at->format('d M Y, h:i A') . ')';
    }

    /**
     * Check if user is online (within last 15 minutes).
     */
    public function getIsOnlineAttribute()
    {
        return $this->last_login_at && $this->last_login_at->gt(now()->subMinutes(15));
    }

    /**
     * Scope a query to filter active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to get online users.
     */
    public function scopeOnline($query)
    {
        return $query->where('last_login_at', '>', now()->subMinutes(15));
    }

    /**
     * Scope a query to get recently logged in users.
     */
    public function scopeRecentlyLoggedIn($query, $days = 7)
    {
        return $query->where('last_login_at', '>', now()->subDays($days));
    }

    public function auditTrail()
    {
        return $this->hasMany(AuditTrail::class, 'user_id');
    }

    /**
     * Get the user's role HTML (returns HTML badge with appropriate colors)
     */

    /**
     * Get the user's status HTML (returns HTML badge with appropriate colors)
     */
    public function getStatusHtmlAttribute()
    {
        $statusText = $this->status_text;
        $badgeClass = $this->status_badge_class;
        $textColor = $badgeClass === 'bg-success' ? 'success' : 'danger';
        $icon = $this->status ? 'ti ti-circle-check' : 'ti ti-circle-x';

        // Return HTML badge
        return '<span class="badge ' . $badgeClass . ' bg-opacity-10 text-' . $textColor . ' px-3 py-1 rounded-pill">
            <i class="' . $icon . ' me-1"></i> ' . $statusText . '
        </span>';
    }

     /**
     * Get the user's status text.
     */
    public function getStatusTextAttribute()
    {
        return $this->status ? 'Active' : 'Inactive';
    }



}
