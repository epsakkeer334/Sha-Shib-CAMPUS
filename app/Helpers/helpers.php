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
