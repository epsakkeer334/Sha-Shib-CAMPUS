<?php

namespace App\Models\Admin;

/**
 * Which courses an institute offers (Institute Management). Inactive = not offered to new students.
 */
class InstituteCourse extends BaseModel
{
    protected $table = 'institute_courses';

    protected $fillable = [
        'institute_id',
        'course_id',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Super Admin sees all; other users only their own institute's courses.
     */
    public function scopeVisibleTo($query, $user)
    {
        if ($user && !$user->isSuperAdmin()) {
            $query->where('institute_id', $user->institute_id);
        }

        return $query;
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
