<?php

namespace App\Models\Admin;

use App\Traits\IsMasterData;

/**
 * Master Data (Module 1A) — managed by Super Admin only.
 */
class PaymentGateway extends BaseModel
{
    use IsMasterData;

    protected $table = 'payment_gateways';

    protected $fillable = [
        'name',
        'code',
        'type',
        'logo',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function getTypeLabelAttribute()
    {
        return config("camp.payment_gateway_types.{$this->type}", $this->type);
    }
}
