<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;

/**
 * A fee a student owes. amount_paid / status are recalculated from successful payments
 * (FeeService::recalculate), never edited by hand.
 */
class StudentDue extends BaseModel
{
    use BelongsToInstitute;

    protected $fillable = [
        'institute_id', 'student_id', 'course_fee_id', 'fee_head', 'amount_due', 'amount_paid', 'due_date', 'status', 'remarks',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function courseFee()
    {
        return $this->belongsTo(CourseFee::class);
    }

    public function payments()
    {
        return $this->hasMany(StudentPayment::class);
    }

    public function getBalanceAttribute(): float
    {
        return $this->status === 'waived' ? 0.0 : max(0, round((float) $this->amount_due - (float) $this->amount_paid, 2));
    }

    /**
     * Balance minus payments still waiting for verification (what may still be recorded).
     */
    public function payableBalance(): float
    {
        $pending = (float) $this->payments()->where('status', 'pending_verification')->sum('amount');

        return max(0, round($this->balance - $pending, 2));
    }

    public function isSettled(): bool
    {
        return in_array($this->status, ['cleared', 'waived'], true);
    }

    public function getStatusHtmlAttribute()
    {
        [$label, $colour] = config("camp.due_statuses.{$this->status}", [ucfirst($this->status), 'secondary']);

        return "<span class='badge badge-soft-{$colour}'>" . e($label) . '</span>';
    }
}
