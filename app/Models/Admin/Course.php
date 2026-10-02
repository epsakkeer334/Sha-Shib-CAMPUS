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
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'total_semesters' => 'integer',
    ];

    public function instituteCourses()
    {
        return $this->hasMany(InstituteCourse::class);
    }

    protected function usageRelations(): array
    {
        return ['instituteCourses'];
    }

    public function getLabelAttribute()
    {
        return "{$this->name} ({$this->code})";
    }
}
