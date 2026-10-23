<?php

namespace App\Services;

use App\Models\Admin\Institute;
use App\Models\Admin\SerialCounter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Central generator for ER numbers, admit cards and marksheet serials.
 * The counter row is locked for the duration of the transaction, so concurrent
 * requests from different institutes can never receive the same number.
 */
class SerialNumberService
{
    public function next(string $seriesKey, ?Institute $institute = null, ?int $year = null): string
    {
        $series = config("camp.serial_series.{$seriesKey}");

        if (!$series) {
            throw new InvalidArgumentException("Unknown serial series [{$seriesKey}].");
        }

        if ($series['per_institute'] && !$institute) {
            throw new InvalidArgumentException("Serial series [{$seriesKey}] needs an institute.");
        }

        $year = $year ?? (int) date('Y');
        $instituteId = $series['per_institute'] ? $institute->id : null;

        // Create the counter row once, under a lock: for group-wide series institute_id is NULL,
        // which the unique index does not protect, so two first requests could both insert.
        $counterId = Cache::lock("serial-counter:{$seriesKey}:" . ($instituteId ?? 'all') . ":{$year}", 10)
            ->block(10, fn () => SerialCounter::firstOrCreate(
                ['series_key' => $seriesKey, 'institute_id' => $instituteId, 'year' => $year],
                ['last_value' => 0, 'prefix_format' => $series['format'], 'pad_length' => $series['pad']]
            )->id);

        return DB::transaction(function () use ($counterId, $institute, $year) {
            $counter = SerialCounter::whereKey($counterId)->lockForUpdate()->firstOrFail();

            $counter->increment('last_value');

            $prefix = strtr($counter->prefix_format, [
                '{institute_code}' => $institute ? $institute->code : '',
                '{year}' => $year,
            ]);

            return $prefix . str_pad($counter->last_value, $counter->pad_length, '0', STR_PAD_LEFT);
        });
    }

    /**
     * A number entered by hand (e.g. an existing student's ER number) that follows the series format,
     * like ER-2024-00087: move that year's counter past it so next() never hands it out again.
     * Values in another format are ignored. Group-wide series only. Returns true when the counter moved.
     */
    public function reserve(string $seriesKey, string $value): bool
    {
        $series = config("camp.serial_series.{$seriesKey}");
        if (!$series || $series['per_institute'] || !str_contains($series['format'], '{year}')) {
            return false;
        }

        $pattern = '/^' . str_replace('\{year\}', '(\d{4})', preg_quote($series['format'], '/')) . '(\d+)$/';
        if (!preg_match($pattern, strtoupper(trim($value)), $m)) {
            return false;
        }
        [$year, $number] = [(int) $m[1], (int) $m[2]];

        $counterId = Cache::lock("serial-counter:{$seriesKey}:all:{$year}", 10)
            ->block(10, fn () => SerialCounter::firstOrCreate(
                ['series_key' => $seriesKey, 'institute_id' => null, 'year' => $year],
                ['last_value' => 0, 'prefix_format' => $series['format'], 'pad_length' => $series['pad']]
            )->id);

        return DB::transaction(function () use ($counterId, $number) {
            $counter = SerialCounter::whereKey($counterId)->lockForUpdate()->firstOrFail();
            if ($counter->last_value >= $number) {
                return false;
            }
            $counter->update(['last_value' => $number]);

            return true;
        });
    }
}
