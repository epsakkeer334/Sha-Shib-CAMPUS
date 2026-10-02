<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Module 2 (2.2–2.5): create the onboarding permissions and give them once to the roles that
 * already exist. Fresh databases get the same defaults from RoleSeeder (config/camp.php).
 * Later changes are made on the Roles & Permissions screen.
 */
return new class extends Migration
{
    protected array $grants = [
        'onboarding.verify_documents' => ['institute-admin'],
        'fees.manage' => ['institute-admin', 'accounts'],
        'payments.collect' => ['institute-admin', 'accounts'],
        'payments.verify' => ['accounts'],
        'enrollment.manage' => ['institute-admin', 'training-manager'],
        // Accounts and TM need to open students they work on.
        'students.view' => ['accounts', 'training-manager'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->grants as $permission => $roles) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

            foreach ($roles as $roleName) {
                // Roles not created yet get their defaults from RoleSeeder.
                if ($role = Role::where('name', $roleName)->where('guard_name', 'web')->first()) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys($this->grants) as $permission) {
            if ($permission !== 'students.view') {
                Permission::where('name', $permission)->where('guard_name', 'web')->delete();
            }
        }
    }
};
