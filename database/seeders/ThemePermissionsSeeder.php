<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Thêm permission cho module Theme/Event Engine (admin/director-only).
 * Usage: php artisan db:seed --class=ThemePermissionsSeeder
 */
class ThemePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = ['view-themes', 'create-themes', 'edit-themes', 'delete-themes', 'manage-themes'];

        foreach ($allPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($allPermissions);
        }

        $directorRole = Role::where('name', 'director')->where('guard_name', 'web')->first();
        if ($directorRole) {
            $directorRole->givePermissionTo($allPermissions);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
