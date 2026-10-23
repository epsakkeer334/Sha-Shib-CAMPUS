<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;

/**
 * A scholarship scheme (Module 2C): institute + course (+ batch / academic year), a percentage or a fixed
 * amount per semester, for one or several semesters. Assigned to students; the discount is calculated on
 * their dues by ScholarshipService.
 */
class Scholarship extends BaseModel
{
    use BelongsToInstitute;

    const TYPES = ['percentage' => 'Percentage', 'fixed' => 'Fixed amount'];
    const APPLIES_TO = ['period_fees' => 'Semester fees only', 'all_fees' => 'All fees (incl. one-time)'];

    protected $fillable = [
        'institute_id', 'course_id', 'batch_id', 'academic_year_id', 'name', 'code', 'description', 'type', 'value', 'applies_to', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where($this->qualifyColumn('status'), true);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function periodRows()
    {
        return $this->hasMany(ScholarshipPeriod::class)->orderBy('period_no');
    }

    public function assignments()
    {
        return $this->hasMany(StudentScholarship::class);
    }

    /** Semester numbers covered, e.g. [1, 2]. */
    public function getPeriodsAttribute(): array
    {
        return $this->periodRows->pluck('period_no')->map(fn ($n) => (int) $n)->sort()->values()->all();
    }

    /** "25 %" or "₹5,000 / semester" */
    public function getValueLabelAttribute(): string
    {
        return $this->type === 'percentage'
            ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.') . '%'
            : money_inr($this->value, false) . ' / ' . strtolower(optional($this->course)->period_label ?: 'semester');
    }

    /** "Semester 1–4" / "Semesters 1, 3" */
    public function getPeriodsLabelAttribute(): string
    {
        $p = $this->periods;
        $label = optional($this->course)->period_label ?: 'Semester';
        if (!$p) {
            return '—';
        }
        $isRange = count($p) > 1 && $p === range($p[0], end($p));

        return count($p) === 1 ? "{$label} {$p[0]}" : \Illuminate\Support\Str::plural($label) . ' ' . ($isRange ? "{$p[0]}–" . end($p) : implode(', ', $p));
    }

    public function covers(?int $periodNo): bool
    {
        return $periodNo === null ? $this->applies_to === 'all_fees' : in_array($periodNo, $this->periods, true);
    }
}
