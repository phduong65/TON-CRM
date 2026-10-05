<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Idempotent seeder — safe to run on existing databases.
 * Quyền XOÁ yêu cầu ĐÃ DUYỆT trong hub "Yêu cầu & Phê duyệt" (nghỉ phép / yêu cầu nhân sự / đổi ca).
 * Xoá yêu cầu đã duyệt sẽ ĐẢO NGƯỢC tác động lúc duyệt (hoàn phép, trừ tăng ca, khôi phục lịch/chấm
 * công...), nên rủi ro cao — CHỈ cấp cho admin, KHÔNG suy ra từ delete-staff-requests/
 * delete-leave-requests/delete-shift-swaps (các quyền đó chỉ để xoá/huỷ đơn CHỜ DUYỆT hoặc TỪ CHỐI).
 * Usage: php artisan db:seed --class=DeleteApprovedRequestsPermissionSeeder
 */
class DeleteApprovedRequestsPermissionSeeder extends Seeder
{
    private const PERMISSION = 'delete-approved-requests';

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
