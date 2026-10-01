<?php

namespace App\Services;

use App\Models\Admin\Institute;
use App\Models\Admin\SerialCounter;
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

        return DB::transaction(function () use ($seriesKey, $series, $institute, $instituteId, $year) {
            $counter = SerialCounter::where('series_key', $seriesKey)
                ->where('institute_id', $instituteId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$counter) {
                $counter = SerialCounter::create([
                    'series_key' => $seriesKey,
                    'institute_id' => $instituteId,
                    'year' => $year,
                    'last_value' => 0,
                    'prefix_format' => $series['format'],
                    'pad_length' => $series['pad'],
                ]);
                $counter = SerialCounter::whereKey($counter->id)->lockForUpdate()->first();
            }

            $counter->increment('last_value');

            $prefix = strtr($counter->prefix_format, [
                '{institute_code}' => $institute ? $institute->code : '',
                '{year}' => $year,
            ]);

            return $prefix . str_pad($counter->last_value, $counter->pad_length, '0', STR_PAD_LEFT);
        });
    }
}
