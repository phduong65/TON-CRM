<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Quyền xem trang Vùng điểm & Redzone (/redzone).
 * Trước đây RedzoneController kiểm tra cứng hasRole(['admin','manager','director']) và route không có
 * middleware — nay dùng permission `view-redzone`, cấp đúng cho 3 vai trò đó nên phạm vi truy cập không đổi.
 * Idempotent: chạy lại nhiều lần không sao (gọi từ DatabaseSeeder và migration 2026_10_03_000001).
 */
class RedzonePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate(['name' => 'view-redzone', 'guard_name' => 'web']);

        foreach (['admin', 'manager', 'director'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo('view-redzone');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
