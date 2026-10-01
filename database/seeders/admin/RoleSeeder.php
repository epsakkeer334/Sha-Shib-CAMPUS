<?php

namespace Database\Seeders\admin;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $superAdminRole = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);

        $email = env('CAMP_SUPER_ADMIN_EMAIL');
        $password = env('CAMP_SUPER_ADMIN_PASSWORD');

        if (!$email || !$password) {
            return;
        }

        $superAdminUser = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('CAMP_SUPER_ADMIN_NAME', 'Sha Shib Admin'),
                'password' => Hash::make($password),
            ]
        );

        if (!$superAdminUser->hasRole($superAdminRole)) {
            $superAdminUser->assignRole($superAdminRole);
        }
    }
}
