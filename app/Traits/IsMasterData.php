<?php

namespace App\Traits;

/**
 * Shared behaviour of Master Data models (Module 1A):
 *  - boolean `status` (Active / Inactive); dropdowns use ->active()
 *  - usageCount(): how many records use this row. A row in use is deactivated, never deleted.
 */
trait IsMasterData
{
    public function initializeIsMasterData(): void
    {
        $this->mergeCasts(['status' => 'boolean']);
    }

    public function scopeActive($query)
    {
        return $query->where($this->qualifyColumn('status'), true);
    }

    /**
     * Relations whose records use this row (relation method names). Override per model.
     */
    protected function usageRelations(): array
    {
        return [];
    }

    public function usageCount(): int
    {
        return collect($this->usageRelations())->sum(fn ($relation) => $this->{$relation}()->count());
    }

    public function isInUse(): bool
    {
        return $this->usageCount() > 0;
    }
}
