<?php

namespace App\Models\Admin;

use App\Traits\IsMasterData;

/**
 * Master Data (Module 1A) — managed by Super Admin only.
 */
class Religion extends BaseModel
{
    use IsMasterData;

    protected $table = 'religions';

    protected $fillable = [
        'name',
        'status',
        'created_by',
        'updated_by',
    ];

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    protected function usageRelations(): array
    {
        return ['categories'];
    }
}
