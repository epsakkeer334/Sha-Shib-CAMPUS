<?php

namespace Database\Seeders\admin;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Roles & permissions from config/camp.php. Safe to re-run: existing roles keep
 * the permissions edited on the Roles & Permissions page. Defaults are applied only
 * to new roles, and a newly added permission is given once to its default roles.
 */
class RoleSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $newPermissions = [];
        foreach (config('camp.permissions') as $group) {
            foreach (array_keys($group) as $permission) {
                if (Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'])->wasRecentlyCreated) {
                    $newPermissions[] = $permission;
                }
            }
        }

        foreach (array_keys(config('camp.roles')) as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($roleName === 'super-admin') {
                continue;
            }

            $defaults = config("camp.default_role_permissions.{$roleName}", []);

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($defaults);
            } elseif ($added = array_intersect($defaults, $newPermissions)) {
                $role->givePermissionTo($added);
            }
        }

        $email = env('CAMP_SUPER_ADMIN_EMAIL');
        $password = env('CAMP_SUPER_ADMIN_PASSWORD');

        if (!$email || !$password) {
            return;
        }

        $superAdminUser = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('CAMP_SUPER_ADMIN_NAME', 'Sha-Shib-CAMPUS Admin'),
                'password' => Hash::make($password),
            ]
        );

        if (!$superAdminUser->hasRole('super-admin')) {
            $superAdminUser->assignRole('super-admin');
        }
    }
}
