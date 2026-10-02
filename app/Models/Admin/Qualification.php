<?php

namespace App\Models\Admin;

use App\Traits\IsMasterData;

/**
 * Master Data (Module 1A) — managed by Super Admin only.
 */
class Qualification extends BaseModel
{
    use IsMasterData;

    protected $table = 'qualifications';

    protected $fillable = [
        'name',
        'status',
        'created_by',
        'updated_by',
    ];
}
