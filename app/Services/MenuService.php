<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class MenuService
{
    /**
     * Sections of config/menu.php visible to the user. Groups keep only their visible
     * children; empty groups and sections are dropped.
     */
    public static function for(?User $user): array
    {
        if (!$user) {
            return [];
        }

        $sections = [];

        foreach (config('menu', []) as $section) {
            $items = static::filterItems($section['items'], $user);

            if ($items) {
                $sections[] = ['title' => $section['title'], 'items' => $items];
            }
        }

        return $sections;
    }

    /**
     * Visible sections with groups flattened to their child links (for quick-link lists).
     */
    public static function flatFor(?User $user): array
    {
        return array_map(function ($section) {
            $links = [];
            foreach ($section['items'] as $item) {
                array_push($links, ...($item['children'] ?? [$item]));
            }

            return ['title' => $section['title'], 'items' => $links];
        }, static::for($user));
    }

    /**
     * Route patterns that make an item (or any of a group's children) active.
     */
    public static function activePatterns(array $item): array
    {
        if (!empty($item['children'])) {
            return array_merge(...array_map([static::class, 'activePatterns'], $item['children']));
        }

        return $item['active'] ?? [$item['route']];
    }

    protected static function filterItems(array $items, User $user): array
    {
        $visible = [];

        foreach ($items as $item) {
            if (isset($item['children'])) {
                $item['children'] = static::filterItems($item['children'], $user);
                if ($item['children']) {
                    $visible[] = $item;
                }
            } elseif (static::visible($item, $user)) {
                $visible[] = $item;
            }
        }

        return $visible;
    }

    protected static function visible(array $item, User $user): bool
    {
        if (!Route::has($item['route'])) {
            return false;
        }

        if (!empty($item['roles']) && !$user->hasAnyRole($item['roles'])) {
            return false;
        }

        if (!empty($item['permission']) && !$user->can($item['permission'])) {
            return false;
        }

        return true;
    }
}
