<?php

namespace App\Models\Admin;

use App\Traits\IsMasterData;

/**
 * Master Data (Module 1A) — managed by Super Admin only.
 */
class Category extends BaseModel
{
    use IsMasterData;

    protected $table = 'categories';

    protected $fillable = [
        'religion_id',
        'name',
        'status',
        'created_by',
        'updated_by',
    ];

    public function religion()
    {
        return $this->belongsTo(Religion::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    protected function usageRelations(): array
    {
        return ['students'];
    }
}
