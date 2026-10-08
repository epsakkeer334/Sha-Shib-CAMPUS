<?php

namespace Database\Seeders\admin;

use App\Models\Admin\AcademicYear;
use Illuminate\Database\Seeder;

/**
 * Academic years from the current one onwards: the running year + the next YEARS_AHEAD
 * (start month from config('camp.academic_year_start_month'), default June →
 * "2026-27" = 1 Jun 2026 – 31 May 2027). Safe to run again: existing years are left as they are.
 */
class AcademicYearSeeder extends Seeder
{
    const YEARS_AHEAD = 4;

    public function run()
    {
        $start = AcademicYear::startFor(now());

        for ($i = 0; $i <= self::YEARS_AHEAD; $i++) {
            $yearStart = $start->copy()->addYears($i);

            AcademicYear::withTrashed()->firstOrCreate(['name' => AcademicYear::nameFor($yearStart)], [
                'start_date' => $yearStart->toDateString(),
                'end_date' => $yearStart->copy()->addYear()->subDay()->toDateString(),
                'status' => true,
            ]);
        }
    }
}
