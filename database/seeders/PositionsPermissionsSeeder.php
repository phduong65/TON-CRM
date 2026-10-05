<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Thêm permission cho module Chức danh (dropdown chọn sẵn thay cho text tự do ở Nhân viên).
 * Usage: php artisan db:seed --class=PositionsPermissionsSeeder
 */
class PositionsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = ['view-positions', 'create-positions', 'edit-positions', 'delete-positions'];

        foreach ($allPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($allPermissions);
        }

        // Cùng nhóm quyền với Chi nhánh (view-branches) — manager/director xem được danh sách
        // chức danh nhưng chỉ admin mới quản lý (thêm/sửa/xóa) để tránh dữ liệu bị nhân bản lệch.
        foreach (['manager', 'director'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo('view-positions');
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
