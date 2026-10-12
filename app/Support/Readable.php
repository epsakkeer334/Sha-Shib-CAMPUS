<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Turns stored keys and values into text people can read (Monitoring detail pop-ups):
 * "fee_head" → "Fee head", true → "Yes", "2026-10-15" → "15 Oct 2026", amount 1500 → "₹1,500.00".
 */
class Readable
{
    const UPPER = ['id' => 'ID', 'er' => 'ER', 'ip' => 'IP', 'upi' => 'UPI', 'utr' => 'UTR', 'tm' => 'TM', 'url' => 'URL', 'kyc' => 'KYC'];

    public static function label(string $key): string
    {
        $words = collect(preg_split('/[_\-\s.]+/', Str::snake($key)))->filter()
            ->map(fn ($w) => self::UPPER[strtolower($w)] ?? strtolower($w));
        if ($words->last() === 'ID' && $words->count() > 1) {
            $words->pop(); // "course_id" → "Course"
        }

        return Str::ucfirst($words->implode(' '));
    }

    public static function value($value, string $key = ''): string
    {
        if ($value === null || $value === '') {
            return 'empty';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return array_values($value) === $value && collect($value)->every(fn ($v) => is_scalar($v) || $v === null)
                ? (count($value) ? implode(', ', array_map(fn ($v) => self::value($v), $value)) : 'none')
                : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $string = (string) $value;
        // status / boolean-like flags stored as 0/1
        if (in_array($string, ['0', '1'], true) && preg_match('/^(status|is_|has_|admission_fee|allow_|advance)/', $key)) {
            return $string === '1' ? 'Yes' : 'No';
        }
        // money
        if (is_numeric($string) && preg_match('/(amount|fee$|balance|total|paid)/', $key) && function_exists('money_inr')) {
            return money_inr((float) $string);
        }
        // dates and date-times
        if (preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?/', $string)) {
            try {
                $date = Carbon::parse($string);

                return strlen($string) > 10 && $date->format('H:i:s') !== '00:00:00' ? $date->format('d M Y, h:i A') : $date->format('d M Y');
            } catch (\Throwable $e) {
            }
        }

        // internal codes such as "period_start" / "pending_verification"
        if (preg_match('/^[a-z]+(_[a-z0-9]+)+$/', $string)) {
            return self::label($string);
        }

        return Str::limit($string, 500);
    }

    /** Short "names" of codes such as er_issued → "ER issued". */
    public static function code(?string $code): string
    {
        return $code ? self::label($code) : '—';
    }
}
