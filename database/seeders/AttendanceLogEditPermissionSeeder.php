<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Thêm quyền tạo/sửa/xoá trực tiếp bản ghi chấm công (Báo cáo chấm công) — CHỈ cấp cho admin,
 * không cấp cho manager/director/team_leader/staff (kể cả các quyền view-attendance/
 * export-attendance sẵn có của họ không kéo theo các quyền này).
 * Usage: php artisan db:seed --class=AttendanceLogEditPermissionSeeder
 */
class AttendanceLogEditPermissionSeeder extends Seeder
{
    private const PERMISSIONS = ['create-attendance-logs', 'edit-attendance-logs', 'delete-attendance-logs'];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo(self::PERMISSIONS);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
