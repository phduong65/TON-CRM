<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Thêm permission cho module Ca làm việc & Chấm công GPS/WiFi.
 * Usage: php artisan db:seed --class=ShiftAttendancePermissionsSeeder
 */
class ShiftAttendancePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $managerPermissions = [
            'view-shifts', 'create-shifts', 'edit-shifts', 'delete-shifts',
            'view-shift-schedules', 'create-shift-schedules', 'edit-shift-schedules', 'delete-shift-schedules',
            'view-attendance-locations', 'create-attendance-locations', 'edit-attendance-locations', 'delete-attendance-locations',
            'view-attendance',
            'checkin-attendance',
            'view-own-attendance',
        ];

        // 'view-own-schedule' KHÔNG nằm trong $managerPermissions vì admin không gắn với employee
        // record — mục "Lịch làm việc" (my-schedule) sẽ 403 nếu admin bấm vào. Chỉ manager/director
        // (thường vẫn có ca làm việc riêng) mới cần quyền này.
        $managerOnlyScheduleView = ['view-own-schedule'];

        // Nhân viên thường: tự chấm công + xem lịch xếp ca (của mình và người khác) — chỉ xem, không được
        // tạo/sửa/xoá vì thiếu quyền create-shift-schedules/delete-shift-schedules. view-own-attendance
        // chỉ cho xem TỔNG HỢP giờ làm/lịch sử chấm công của chính mình (khác view-attendance — xem của
        // mọi nhân viên, dành cho HR/Manager ở trang /attendance-logs).
        $employeeOnlyPermissions = ['checkin-attendance', 'view-shift-schedules', 'view-own-schedule', 'view-own-attendance'];

        foreach (array_merge($managerPermissions, $managerOnlyScheduleView) as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($managerPermissions);
            $adminRole->revokePermissionTo('view-own-schedule');
        }

        foreach (['manager', 'director'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo(array_merge($managerPermissions, $managerOnlyScheduleView));
            }
        }

        foreach (['team_leader', 'staff'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($employeeOnlyPermissions);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
