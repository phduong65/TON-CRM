<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Thêm permission cho module Ngày nghỉ lễ (admin/HR-only, giống Settings).
 * Usage: php artisan db:seed --class=HolidaysPermissionsSeeder
 */
class HolidaysPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = ['view-holidays', 'create-holidays', 'edit-holidays', 'delete-holidays'];

        foreach ($allPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($allPermissions);
        }

        foreach (['manager', 'director'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($allPermissions);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
