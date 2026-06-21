<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $directorRole = Role::firstOrCreate(['name' => 'director', 'guard_name' => 'web']);

        $permissions = [
            // Nhân sự (view only)
            'view-employees',
            // Cần thiết để form tạo phạt/thưởng hoạt động
            'view-teams', 'view-branches',
            'view-violations',
            'view-reward-types', 'view-reward-categories',
            // Phạt
            'view-penalties', 'create-penalties', 'delete-penalties',
            'approve-penalties', 'revoke-penalties',
            'import-attendance',
            // Thưởng
            'view-rewards', 'create-rewards', 'delete-rewards',
            'approve-rewards', 'revoke-rewards',
            // Quy chế
            'view-regulations', 'create-regulations', 'edit-regulations', 'delete-regulations',
            // Báo cáo
            'view-reports', 'create-reports', 'approve-reports',
            // Khiếu nại
            'create-appeals', 'view-appeals', 'review-appeals',
            // Hệ thống
            'view-activity-log',
            'manage-settings',
            'view-notifications', 'create-notifications',
        ];

        $directorRole->syncPermissions(
            Permission::whereIn('name', $permissions)->where('guard_name', 'web')->get()
        );
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::where('name', 'director')->delete();
    }
};
