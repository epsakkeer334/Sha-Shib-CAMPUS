<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;

/**
 * Fee structure line for a course at an institute. Student dues are generated from these.
 */
class CourseFee extends BaseModel
{
    use BelongsToInstitute;

    protected $fillable = ['institute_id', 'course_id', 'fee_head', 'amount', 'due_days', 'sort_order', 'status', 'created_by', 'updated_by'];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_days' => 'integer',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    protected $appends = ['amount_label'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function dues()
    {
        return $this->hasMany(StudentDue::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function getAmountLabelAttribute()
    {
        return money_inr($this->amount);
    }
}
