<?php

use Illuminate\Support\Facades\Request;

if (!function_exists('isActiveMenu')) {
    function isActiveMenu(array $routes): bool
    {
        foreach ($routes as $route) {
            if (Request::routeIs($route)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('money_inr')) {
    /**
     * Indian-format rupee amount: 123456.5 → "₹1,23,456.50" (or "₹1,23,457" without decimals).
     */
    function money_inr($amount, bool $decimals = true): string
    {
        $amount = round((float) $amount, 2);
        $negative = $amount < 0;
        [$whole, $fraction] = explode('.', number_format(abs($amount), 2, '.', ''));

        // Last 3 digits, then groups of 2 (lakh / crore).
        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $grouped = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last3 : $last3;

        return ($negative ? '-' : '') . '₹' . $grouped . ($decimals ? '.' . $fraction : '');
    }
}
