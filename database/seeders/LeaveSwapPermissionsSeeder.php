<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Thêm permission cho module Xin nghỉ phép & Đổi ca.
 * Usage: php artisan db:seed --class=LeaveSwapPermissionsSeeder
 */
class LeaveSwapPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = [
            'view-leave-requests', 'create-leave-requests', 'approve-leave-requests',
            'view-shift-swaps', 'create-shift-swaps', 'approve-shift-swaps',
        ];

        // Nhân viên thường: tự tạo/xem đơn của mình, không được duyệt.
        $employeePermissions = [
            'view-leave-requests', 'create-leave-requests',
            'view-shift-swaps', 'create-shift-swaps',
        ];

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

        foreach (['team_leader', 'staff'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($employeePermissions);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
