<?php

namespace App\Models\Admin;

use App\Traits\IsMasterData;

/**
 * Master Data (Module 1A) — managed by Super Admin only.
 */
class Course extends BaseModel
{
    use IsMasterData;

    protected $table = 'courses';

    protected $fillable = [
        'name',
        'code',
        'duration_months',
        'total_semesters',
        'period_label',
        'period_months',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'total_semesters' => 'integer',
        'period_months' => 'integer',
    ];

    /** Number of academic periods of the course (semesters / terms / modules …). */
    public function getTotalPeriodsAttribute(): int
    {
        return max(1, (int) $this->total_semesters);
    }

    /** Length of one period in months (falls back to duration ÷ periods). */
    public function getPeriodLengthAttribute(): int
    {
        return max(1, (int) ($this->period_months ?: round(((int) $this->duration_months ?: 12) / $this->total_periods)));
    }

    public function periodsPerYear(): int
    {
        return max(1, intdiv(12, min(12, $this->period_length)));
    }

    public function yearOfStudy(int $periodNo): int
    {
        return (int) ceil($periodNo / $this->periodsPerYear());
    }

    /** "Semester 3", "Term 2" … */
    public function periodName(?int $periodNo): string
    {
        return $periodNo ? (($this->period_label ?: 'Semester') . ' ' . $periodNo) : 'One-time';
    }

    /** "8 × Semester · 6 months" */
    public function getPeriodSummaryAttribute(): string
    {
        return $this->total_periods . ' × ' . ($this->period_label ?: 'Semester') . ' · ' . $this->period_length . ' ' . ($this->period_length === 1 ? 'month' : 'months');
    }

    public function instituteCourses()
    {
        return $this->hasMany(InstituteCourse::class);
    }

    protected function usageRelations(): array
    {
        return ['instituteCourses', 'students'];
    }

    public function getLabelAttribute()
    {
        return "{$this->name} ({$this->code})";
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
