<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Thêm quyền "Đăng nhập hộ" (impersonate) tài khoản nhân viên khác — CHỈ cấp cho admin,
 * cùng nguyên tắc với AttendanceLogEditPermissionSeeder (không cấp cho manager/director/
 * team_leader/staff).
 * Usage: php artisan db:seed --class=ImpersonateUsersPermissionSeeder
 */
class ImpersonateUsersPermissionSeeder extends Seeder
{
    private const PERMISSION = 'impersonate-users';

    public function run(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo(self::PERMISSION);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
