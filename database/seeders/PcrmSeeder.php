<?php

namespace Database\Seeders;

use App\Models\Regulation;
use App\Models\Setting;
use App\Models\Violation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PcrmSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Default settings
        Setting::create(['key' => 'default_score_per_month',    'value' => '100', 'description' => 'Điểm mặc định cấp cho mỗi nhân viên đầu tháng']);
        Setting::create(['key' => 'greenzone_min',              'value' => '90',  'description' => 'Ngưỡng tối thiểu để xếp vào Greenzone (90–100đ)']);
        Setting::create(['key' => 'yellowzone_min',             'value' => '80',  'description' => 'Ngưỡng tối thiểu để xếp vào Yellowzone (80–89đ)']);
        Setting::create(['key' => 'orangezone_min',             'value' => '70',  'description' => 'Ngưỡng tối thiểu để xếp vào Orangezone (70–79đ)']);
        Setting::create(['key' => 'consecutive_redzone_months', 'value' => '2',   'description' => 'Số tháng Redzone liên tiếp để kích hoạt cảnh báo xử phạt đặc biệt']);
        Setting::create(['key' => 'company_name',               'value' => 'Công ty TNHH F&B', 'description' => 'Tên công ty']);
        Setting::create(['key' => 'rows_per_page',              'value' => '15',  'description' => 'Số dòng mỗi trang']);
        Setting::create(['key' => 'report_reward_points',       'value' => '5',   'description' => 'Điểm thưởng cho nhân viên khi báo cáo chéo được duyệt']);

        // 2. Admin user
        $admin = User::create([
            'name'     => 'Quản trị viên',
            'email'    => 'admin@hr.vn',
            'password' => Hash::make('admin123'),
        ]);

        // 2b. Roles & Permissions — dùng firstOrCreate để tránh conflict với migrations
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = [
            'view-employees', 'create-employees', 'edit-employees', 'delete-employees',
            'view-teams', 'create-teams', 'edit-teams', 'delete-teams',
            'view-branches', 'create-branches', 'edit-branches', 'delete-branches',
            'view-violations', 'create-violations', 'edit-violations', 'delete-violations',
            'view-penalties', 'create-penalties', 'approve-penalties', 'import-attendance',
            'view-regulations', 'create-regulations', 'edit-regulations', 'delete-regulations',
            'view-rewards', 'create-rewards', 'delete-rewards', 'approve-rewards',
            'view-reward-types', 'create-reward-types', 'edit-reward-types', 'delete-reward-types',
            'view-reward-categories', 'create-reward-categories', 'edit-reward-categories', 'delete-reward-categories',
            'view-reports', 'create-reports', 'approve-reports',
            'view-activity-log',
            'manage-settings',
            'manage-users',
            'manage-roles',
            'view-notifications',
            'create-notifications',
            'view-log-viewer',
        ];

        foreach ($allPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($allPermissions);

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->syncPermissions([
            'view-employees', 'create-employees', 'edit-employees',
            'view-teams', 'view-branches',
            'view-violations',
            'view-penalties', 'create-penalties', 'approve-penalties', 'import-attendance',
            'view-regulations',
            'view-rewards', 'create-rewards', 'approve-rewards',
            'view-reward-types',
            'view-reward-categories',
            'view-reports', 'create-reports', 'approve-reports',
            'view-activity-log',
            'view-notifications', 'create-notifications',
        ]);

        $teamLeaderRole = Role::firstOrCreate(['name' => 'team_leader', 'guard_name' => 'web']);
        $teamLeaderRole->syncPermissions([
            'view-employees', 'view-teams', 'view-branches',
            'view-penalties', 'create-penalties',
            'view-rewards', 'create-rewards',
            'view-reward-types',
            'view-reward-categories',
            'view-reports', 'create-reports',
            'view-notifications',
        ]);

        $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $staffRole->syncPermissions([
            'view-employees', 'view-penalties',
            'view-rewards',
            'view-reports', 'create-reports',
            'view-notifications',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $admin->assignRole('admin');

        // Regulations & Violations được quản lý bởi ViolationsSeeder (chạy sau).
    }
}
