<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Adds view-log-viewer permission to admin role.
 * Usage: php artisan db:seed --class=LogViewerSeeder
 */
class LogViewerSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate(['name' => 'view-log-viewer', 'guard_name' => 'web']);

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo('view-log-viewer');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
