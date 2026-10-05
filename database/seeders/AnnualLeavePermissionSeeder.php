<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Quyền xem báo cáo tổng hợp "Phép năm" (tất cả nhân viên văn phòng đủ điều kiện cùng lúc)
 * — xem AnnualLeaveController.
 * Usage: php artisan db:seed --class=AnnualLeavePermissionSeeder
 */
class AnnualLeavePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate(['name' => 'view-annual-leave', 'guard_name' => 'web']);

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo('view-annual-leave');
        }

        foreach (['manager', 'director'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo('view-annual-leave');
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
