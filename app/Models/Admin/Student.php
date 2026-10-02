<?php

namespace App\Models\Admin;

use App\Models\User;
use App\Traits\BelongsToInstitute;

/**
 * Student (Module 2). Institute-scoped: non-Super-Admin users only ever see their institute's students.
 */
class Student extends BaseModel
{
    use BelongsToInstitute;

    // Statuses in which onboarding details may still be edited from the admin panel.
    const EDITABLE_STATUSES = ['draft', 'pending_docs', 'pending_approval', 'rejected'];

    protected $fillable = [
        'institute_id', 'user_id', 'course_id', 'er_number',
        'first_name', 'last_name', 'dob', 'gender', 'qualification_id', 'email', 'phone', 'emergency_contact',
        'religion_id', 'category_id', 'joining_date', 'onboarding_deadline',
        'address', 'country_id', 'state_id', 'city', 'pincode',
        'parent_name', 'parent_phone', 'parent_email', 'parent_occupation',
        'status', 'submitted_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'dob' => 'date',
        'joining_date' => 'date',
        'onboarding_deadline' => 'date',
        'submitted_at' => 'datetime',
    ];

    protected $appends = ['full_name', 'status_html', 'formatted_joining_date', 'formatted_onboarding_deadline'];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function qualification()
    {
        return $this->belongsTo(Qualification::class);
    }

    public function religion()
    {
        return $this->belongsTo(Religion::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function academicDetail()
    {
        return $this->hasOne(StudentAcademicDetail::class);
    }

    public function documents()
    {
        return $this->hasMany(StudentDocument::class);
    }

    // Onboarding progress

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public function hasAddressDetails(): bool
    {
        return filled($this->address) && $this->country_id && $this->state_id && filled($this->city) && filled($this->pincode)
            && filled($this->parent_name) && filled($this->parent_phone);
    }

    /**
     * Required KYC document types not uploaded yet (rejected uploads count as missing).
     */
    public function missingRequiredDocuments(): array
    {
        $uploaded = $this->documents()->where('verification_status', '!=', 'rejected')->pluck('document_type')->unique()->all();

        return collect(config('camp.student_document_types'))
            ->filter(fn ($type) => $type[1])
            ->keys()
            ->diff($uploaded)
            ->values()
            ->all();
    }

    // Accessors

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getStatusLabelAttribute()
    {
        return config("camp.student_statuses.{$this->status}.0", ucfirst($this->status));
    }

    public function getStatusHtmlAttribute()
    {
        $colour = config("camp.student_statuses.{$this->status}.1", 'secondary');

        return "<span class='badge badge-soft-{$colour}'>" . e($this->status_label) . '</span>';
    }

    public function getFormattedJoiningDateAttribute()
    {
        return $this->joining_date ? $this->joining_date->format('d M Y') : '-';
    }

    public function getFormattedOnboardingDeadlineAttribute()
    {
        return $this->onboarding_deadline ? $this->onboarding_deadline->format('d M Y') : '-';
    }
}
