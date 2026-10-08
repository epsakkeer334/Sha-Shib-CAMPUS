<?php

namespace App\Models\Admin;

use App\Traits\IsMasterData;
use Carbon\Carbon;

/**
 * Master Data (Super Admin): an academic year, e.g. "2026-27" from 1 Jun 2026 to 31 May 2027.
 * Academic periods (semesters / terms …) and period fee lines refer to it (Module 2B).
 */
class AcademicYear extends BaseModel
{
    use IsMasterData;

    protected $table = 'academic_years';

    protected $fillable = ['name', 'start_date', 'end_date', 'status', 'created_by', 'updated_by'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function coursePeriods()
    {
        return $this->hasMany(CoursePeriod::class);
    }

    public function courseFees()
    {
        return $this->hasMany(CourseFee::class);
    }

    protected function usageRelations(): array
    {
        return ['coursePeriods', 'courseFees'];
    }

    /** Active academic year containing the date (null when none is set up). */
    public static function forDate($date): ?self
    {
        if (!$date) {
            return null;
        }
        $day = Carbon::parse($date)->toDateString();

        return static::active()->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day)->orderByDesc('start_date')->first();
    }

    public static function current(): ?self
    {
        return static::forDate(now());
    }

    /** Start of the academic year containing $date (start month from config, default June). */
    public static function startFor($date): Carbon
    {
        $date = Carbon::parse($date);
        $month = (int) config('camp.academic_year_start_month', 6);
        $year = $date->month >= $month ? $date->year : $date->year - 1;

        return Carbon::create($year, $month, 1)->startOfDay();
    }

    public static function nameFor(Carbon $start): string
    {
        return $start->year . '-' . substr((string) ($start->year + 1), -2);
    }

    public function getIsCurrentAttribute(): bool
    {
        return $this->start_date && $this->end_date && now()->between($this->start_date->copy()->startOfDay(), $this->end_date->copy()->endOfDay());
    }

    public function getPeriodLabelAttribute(): string
    {
        return optional($this->start_date)->format('d M Y') . ' – ' . optional($this->end_date)->format('d M Y');
    }
}
