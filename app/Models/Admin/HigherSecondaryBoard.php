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
        'status',
        'created_by',
        'updated_by',
    ];
}
