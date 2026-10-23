<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;
use Illuminate\Database\Eloquent\Model;

/**
 * A scholarship given to a student (Module 2C). One per student + scholarship; a revoked one can be given again.
 */
class StudentScholarship extends Model
{
    use BelongsToInstitute;

    protected $fillable = ['institute_id', 'student_id', 'scholarship_id', 'status', 'assigned_by', 'assigned_at', 'revoked_by', 'revoked_at', 'remarks'];

    protected $casts = ['assigned_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function scopeActive($query)
    {
        return $query->where($this->qualifyColumn('status'), 'active');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function scholarship()
    {
        return $this->belongsTo(Scholarship::class)->withTrashed();
    }

    public function discounts()
    {
        return $this->hasMany(StudentDueDiscount::class);
    }

    public function assigner()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_by');
    }

    public function revoker()
    {
        return $this->belongsTo(\App\Models\User::class, 'revoked_by');
    }
}
