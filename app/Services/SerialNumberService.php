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
}
