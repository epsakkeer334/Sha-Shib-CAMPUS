<?php

namespace App\Models\Admin;

/**
 * Student Academic Information (Module 2) — one row per student.
 */
class StudentAcademicDetail extends BaseModel
{
    protected $fillable = [
        'student_id',
        'matriculation_board_id', 'matriculation_mark_type', 'matriculation_mark',
        'higher_secondary_board_id', 'higher_secondary_subject', 'qualifying_subject_group_id', 'higher_secondary_mark_type', 'higher_secondary_mark',
        'eligibility_status', 'eligibility_snapshot', 'eligibility_checked_at',
        'eligibility_override_by', 'eligibility_override_at', 'eligibility_override_reason',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'matriculation_mark' => 'decimal:2',
        'higher_secondary_mark' => 'decimal:2',
        'eligibility_snapshot' => 'array',
        'eligibility_checked_at' => 'datetime',
        'eligibility_override_at' => 'datetime',
    ];

    // eligibility_status => [label, colour]
    const ELIGIBILITY = [
        'eligible' => ['Eligible', 'success'],
        'not_eligible' => ['Not eligible', 'danger'],
        'overridden' => ['Allowed by admin', 'warning'],
        'not_configured' => ['No criteria set', 'secondary'],
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function matriculationBoard()
    {
        return $this->belongsTo(MatriculationBoard::class);
    }

    public function higherSecondaryBoard()
    {
        return $this->belongsTo(HigherSecondaryBoard::class);
    }

    /** 12th subject group / stream (its code is kept in `higher_secondary_subject`). */
    public function subjectGroup()
    {
        return $this->belongsTo(QualifyingSubjectGroup::class, 'qualifying_subject_group_id');
    }

    public function eligibilityOverrider()
    {
        return $this->belongsTo(\App\Models\User::class, 'eligibility_override_by');
    }

    public function getEligibilityLabelAttribute(): ?string
    {
        return self::ELIGIBILITY[$this->eligibility_status][0] ?? null;
    }

    public function getEligibilityColourAttribute(): string
    {
        return self::ELIGIBILITY[$this->eligibility_status][1] ?? 'secondary';
    }

    /**
     * Mark followed by its unit, e.g. "92.50 %" or "9.10 CGPA".
     */
    public static function formatMark($mark, ?string $type): string
    {
        if ($mark === null) {
            return '-';
        }

        return $type === 'cgpa' ? "{$mark} CGPA" : "{$mark} %";
    }
}
