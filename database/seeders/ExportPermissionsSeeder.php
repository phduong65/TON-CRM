<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Thêm permission xuất Excel cho Xếp ca, Báo cáo chấm công, Lịch làm việc cá nhân.
 * Usage: php artisan db:seed --class=ExportPermissionsSeeder
 */
class ExportPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $managerExportPermissions = ['export-shift-schedules', 'export-attendance'];
        $everyoneExportPermissions = ['export-own-schedule'];

        foreach (array_merge($managerExportPermissions, $everyoneExportPermissions) as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        foreach (['admin', 'manager', 'director'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($managerExportPermissions);
            }
        }

        foreach (['admin', 'manager', 'director', 'team_leader', 'staff'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($everyoneExportPermissions);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
