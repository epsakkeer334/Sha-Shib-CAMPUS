<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;

/**
 * A payment method an institute accepts, with its own UPI ID / QR / instructions.
 * Students only see methods enabled here (and active in Master Data).
 */
class InstitutePaymentGateway extends BaseModel
{
    use BelongsToInstitute;

    protected $fillable = [
        'institute_id', 'payment_gateway_id', 'payee_name', 'upi_id', 'qr_code_path', 'instructions',
        'merchant_id', 'credentials', 'is_test_mode', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'is_test_mode' => 'boolean',
        'status' => 'boolean',
    ];

    protected $hidden = ['credentials'];

    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('status', true)->whereHas('gateway', fn ($g) => $g->where('status', true));
    }

    /**
     * UPI deep link (opens GPay / PhonePe / any UPI app on a phone) for the given amount.
     */
    public function upiLink(float $amount, string $note): ?string
    {
        if (!$this->upi_id) {
            return null;
        }

        return 'upi://pay?' . http_build_query([
            'pa' => $this->upi_id,
            'pn' => $this->payee_name ?: optional($this->institute)->name,
            'am' => number_format($amount, 2, '.', ''),
            'cu' => 'INR',
            'tn' => mb_substr($note, 0, 60),
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
