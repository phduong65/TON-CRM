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

        // 2. Admin user
        $admin = User::create([
            'name'     => 'Quản trị viên',
            'email'    => 'admin@pcrm.vn',
            'password' => Hash::make('admin123'),
        ]);

        // 2b. Roles & Permissions
        $permissions = [
            'view-employees', 'create-employees', 'edit-employees', 'delete-employees',
            'view-teams', 'create-teams', 'edit-teams', 'delete-teams',
            'view-branches', 'create-branches', 'edit-branches', 'delete-branches',
            'view-violations', 'create-violations', 'edit-violations', 'delete-violations',
            'view-penalties', 'create-penalties', 'approve-penalties', 'import-attendance',
            'view-regulations', 'create-regulations', 'edit-regulations', 'delete-regulations',
            'view-activity-log',
            'manage-settings',
            'manage-users',
            'manage-roles',
            'view-notifications',
            'create-notifications',
        ];

        foreach ($permissions as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($permissions);

        $managerRole = Role::create(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->givePermissionTo([
            'view-employees', 'create-employees', 'edit-employees',
            'view-teams', 'view-branches',
            'view-violations',
            'view-penalties', 'create-penalties', 'approve-penalties', 'import-attendance',
            'view-regulations',
            'view-activity-log',
            'view-notifications', 'create-notifications',
        ]);

        $teamLeaderRole = Role::create(['name' => 'team_leader', 'guard_name' => 'web']);
        $teamLeaderRole->givePermissionTo([
            'view-employees', 'view-teams', 'view-branches',
            'view-penalties', 'create-penalties',
            'view-notifications',
        ]);

        $staffRole = Role::create(['name' => 'staff', 'guard_name' => 'web']);
        $staffRole->givePermissionTo([
            'view-employees', 'view-penalties',
            'view-notifications',
        ]);

        $admin->assignRole('admin');

        // 3. Regulations
        $reg1 = Regulation::create([
            'name'        => 'Quy chế giờ giấc làm việc',
            'description' => 'Xử phạt đối với hành vi đi trễ, về sớm',
        ]);
        $reg2 = Regulation::create([
            'name'        => 'Quy chế trang phục',
            'description' => 'Vi phạm quy định về đồng phục và trang phục làm việc',
        ]);
        $reg3 = Regulation::create([
            'name'        => 'Quy chế vệ sinh an toàn thực phẩm',
            'description' => 'Vi phạm quy định vệ sinh trong khu vực bếp và pha chế',
        ]);
        $reg4 = Regulation::create([
            'name'        => 'Quy chế hành vi ứng xử',
            'description' => 'Vi phạm về thái độ phục vụ khách hàng',
        ]);
        $reg5 = Regulation::create([
            'name'        => 'Quy chế an toàn lao động',
            'description' => 'Vi phạm quy định an toàn trong quá trình làm việc',
        ]);

        // 4. Violations
        Violation::create(['name' => 'Đi trễ dưới 15 phút',                'severity' => 'low',      'regulation_id' => $reg1->id]);
        Violation::create(['name' => 'Đi trễ 15-30 phút',                  'severity' => 'medium',   'regulation_id' => $reg1->id]);
        Violation::create(['name' => 'Đi trễ trên 30 phút',                'severity' => 'high',     'regulation_id' => $reg1->id]);
        Violation::create(['name' => 'Không mặc đồng phục',                'severity' => 'medium',   'regulation_id' => $reg2->id]);
        Violation::create(['name' => 'Mặc đồng phục không đúng quy định',  'severity' => 'low',      'regulation_id' => $reg2->id]);
        Violation::create(['name' => 'Khu vực làm việc không vệ sinh',     'severity' => 'high',     'regulation_id' => $reg3->id]);
        Violation::create(['name' => 'Thái độ không tốt với khách hàng',   'severity' => 'critical', 'regulation_id' => $reg4->id]);
        Violation::create(['name' => 'Không sử dụng thiết bị bảo hộ',     'severity' => 'high',     'regulation_id' => $reg5->id]);
    }
}
