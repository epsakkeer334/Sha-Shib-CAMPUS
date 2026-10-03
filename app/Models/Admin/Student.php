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

    public function dues()
    {
        return $this->hasMany(StudentDue::class);
    }

    public function payments()
    {
        return $this->hasMany(StudentPayment::class);
    }

    public function approvals()
    {
        return $this->hasMany(EnrollmentApproval::class);
    }

    public function erRequest()
    {
        return $this->hasOne(ErRequest::class);
    }

    public function idCard()
    {
        return $this->hasOne(IdCard::class);
    }

    /**
     * One gate of the dual gate (EnrollmentApproval::DOCUMENTS / ::FEES), or null before submission.
     */
    public function gate(string $gate): ?EnrollmentApproval
    {
        return $this->relationLoaded('approvals')
            ? $this->approvals->firstWhere('gate', $gate)
            : $this->approvals()->where('gate', $gate)->first();
    }

    public function gateApproved(string $gate): bool
    {
        return optional($this->gate($gate))->status === 'approved';
    }

    /**
     * Every required KYC document type has a verified file.
     */
    public function requiredDocumentsVerified(): bool
    {
        $verified = $this->documents()->where('verification_status', 'verified')->pluck('document_type')->unique();

        return collect(config('camp.student_document_types'))
            ->filter(fn ($type) => $type[1])
            ->keys()
            ->diff($verified)
            ->isEmpty();
    }

    public function outstandingAmount(): float
    {
        return round($this->dues()->whereNotIn('status', ['cleared', 'waived'])->get()->sum(fn ($due) => $due->balance), 2);
    }

    public function getDaysToDeadlineAttribute(): ?int
    {
        return $this->onboarding_deadline ? (int) now()->startOfDay()->diffInDays($this->onboarding_deadline, false) : null;
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(mb_substr($this->first_name, 0, 1) . mb_substr($this->last_name, 0, 1));
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

    /**
     * Fees column of the students list: No fees / Paid / Being confirmed / Part paid / Unpaid.
     */
    public function getFeeStatusHtmlAttribute()
    {
        $dues = $this->relationLoaded('dues') ? $this->dues : $this->dues()->get();
        if ($dues->isEmpty()) {
            return "<span class='badge badge-soft-secondary'>No fees</span>";
        }

        $outstanding = $dues->whereNotIn('status', ['cleared', 'waived'])->sum(fn ($d) => $d->balance);
        $pending = $this->payments()->where('status', 'pending_verification')->sum('amount');

        if ($outstanding <= 0) {
            return "<span class='badge badge-soft-success'>Paid</span>";
        }

        $html = $dues->sum('amount_paid') > 0
            ? "<span class='badge badge-soft-warning'>Part paid</span>"
            : "<span class='badge badge-soft-danger'>Unpaid</span>";
        $html .= "<div class='small text-muted mt-1'>" . e(money_inr($outstanding, false)) . ' due</div>';

        if ($pending > 0) {
            $html .= "<div class='small text-info'>" . e(money_inr($pending, false)) . ' to verify</div>';
        }

        return $html;
    }

    public function getDocumentsGateHtmlAttribute()
    {
        return $this->gateBadge(EnrollmentApproval::DOCUMENTS);
    }

    public function getFeesGateHtmlAttribute()
    {
        return $this->gateBadge(EnrollmentApproval::FEES);
    }

    protected function gateBadge(string $gate): string
    {
        $status = optional($this->gate($gate))->status;
        [$label, $colour] = [
            'approved' => ['Approved', 'success'],
            'rejected' => ['Rejected', 'danger'],
            'pending' => ['Pending', 'info'],
        ][$status] ?? ['Not submitted', 'secondary'];

        return "<span class='badge badge-soft-{$colour}'>{$label}</span>";
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
