<?php

namespace App\Models\Admin;

use App\Models\User;
use App\Traits\BelongsToInstitute;

/**
 * One payment by a student. GPay/UPI and offline payments wait in "pending_verification"
 * until Accounts approves them (receipt issued) or rejects them (status "failed").
 */
class StudentPayment extends BaseModel
{
    use BelongsToInstitute;

    protected $fillable = [
        'institute_id', 'student_id', 'student_due_id', 'payment_gateway_id', 'amount', 'currency',
        'gateway_order_id', 'gateway_payment_id', 'transaction_reference', 'payer_upi_id', 'proof_file_path',
        'status', 'paid_at', 'verified_by', 'verified_at', 'receipt_number', 'gateway_response',
        'refund_amount', 'refunded_at', 'remarks', 'rejection_reason', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'paid_at' => 'date',
        'verified_at' => 'datetime',
        'refunded_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function due()
    {
        return $this->belongsTo(StudentDue::class, 'student_due_id');
    }

    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute()
    {
        return config("camp.payment_statuses.{$this->status}.0", ucfirst($this->status));
    }

    public function getStatusHtmlAttribute()
    {
        $colour = config("camp.payment_statuses.{$this->status}.1", 'secondary');

        return "<span class='badge badge-soft-{$colour}'>" . e($this->status_label) . '</span>';
    }

    public function getProofIsImageAttribute(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png)$/i', (string) $this->proof_file_path);
    }
}
