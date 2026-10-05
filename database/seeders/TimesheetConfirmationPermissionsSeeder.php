<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Thêm permission cho tính năng "Xác nhận công" (monthly timesheet confirmation).
 * - view-own-timesheet-confirmation: tự xem/xác nhận bảng công tháng của mình — cấp rộng
 *   cho mọi role có employee record, giống view-own-attendance.
 * - view-timesheet-confirmations / confirm-timesheet-on-behalf: HR/Admin xem trạng thái xác
 *   nhận của tất cả nhân viên + xác nhận hộ — chỉ cấp cho admin/manager/director.
 * Usage: php artisan db:seed --class=TimesheetConfirmationPermissionsSeeder
 */
class TimesheetConfirmationPermissionsSeeder extends Seeder
{
    private const SELF_PERMISSION = 'view-own-timesheet-confirmation';

    private const ADMIN_PERMISSIONS = ['view-timesheet-confirmations', 'confirm-timesheet-on-behalf'];

    public function run(): void
    {
        Permission::firstOrCreate(['name' => self::SELF_PERMISSION, 'guard_name' => 'web']);
        foreach (self::ADMIN_PERMISSIONS as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        foreach (['admin', 'manager', 'director', 'team_leader', 'staff'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo(self::SELF_PERMISSION);
        }

        foreach (['admin', 'manager', 'director'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo(self::ADMIN_PERMISSIONS);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
