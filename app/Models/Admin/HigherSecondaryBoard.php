<?php

namespace App\Models\Admin;

use App\Traits\IsMasterData;

/**
 * Master Data (Module 1A) — managed by Super Admin only.
 */
class HigherSecondaryBoard extends BaseModel
{
    use IsMasterData;

    protected $table = 'higher_secondary_boards';

    protected $fillable = [
        'name',
        'cgpa_factor', // CGPA × factor = % (course eligibility)
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = ['cgpa_factor' => 'decimal:2'];

    public function eligibilityCriteria()
    {
        return $this->hasMany(CourseEligibilityCriterion::class);
    }

    public function academicDetails()
    {
        return $this->hasMany(StudentAcademicDetail::class);
    }

    protected function usageRelations(): array
    {
        return ['academicDetails', 'eligibilityCriteria'];
    }
}
