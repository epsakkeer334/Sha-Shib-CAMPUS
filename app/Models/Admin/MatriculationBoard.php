<?php

namespace App\Models\Admin;

use App\Traits\IsMasterData;

/**
 * Master Data (Module 1A) — managed by Super Admin only.
 */
class MatriculationBoard extends BaseModel
{
    use IsMasterData;

    protected $table = 'matriculation_boards';

    protected $fillable = [
        'name',
        'status',
        'created_by',
        'updated_by',
    ];
}
