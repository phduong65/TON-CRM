<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Thêm permission cho module "Yêu cầu và Phê duyệt" (4 loại mới: Lượt chấm công,
 * Công tác/Ra ngoài, Đi muộn về sớm, Thay đổi giờ vào/ra — dùng chung bảng staff_requests).
 * Nghỉ phép & Đổi ca làm dùng permission riêng đã có (xem LeaveSwapPermissionsSeeder).
 * Usage: php artisan db:seed --class=StaffRequestsPermissionsSeeder
 */
class StaffRequestsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = ['view-staff-requests', 'create-staff-requests', 'approve-staff-requests'];

        // Nhân viên thường: tự tạo/xem yêu cầu của mình, không được duyệt.
        $employeePermissions = ['view-staff-requests', 'create-staff-requests'];

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
