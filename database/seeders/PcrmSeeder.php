<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Team;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Regulation;
use App\Models\Violation;
use App\Models\EmployeeScore;
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
        Setting::create(['key' => 'redzone_threshold', 'value' => '50', 'description' => 'Ngưỡng cảnh báo Redzone']);
        Setting::create(['key' => 'default_score_per_month', 'value' => '100', 'description' => 'Điểm mặc định mỗi tháng']);
        Setting::create(['key' => 'company_name', 'value' => 'Công ty TNHH F&B', 'description' => 'Tên công ty']);
        Setting::create(['key' => 'rows_per_page', 'value' => '15', 'description' => 'Số dòng mỗi trang']);

        // 2. Admin user
        $admin = User::create([
            'name' => 'Quản trị viên',
            'email' => 'admin@pcrm.vn',
            'password' => Hash::make('admin123'),
        ]);

        // 2b. Roles & Permissions
        $permissions = [
            'view-employees', 'create-employees', 'edit-employees', 'delete-employees',
            'view-teams', 'create-teams', 'edit-teams', 'delete-teams',
            'view-branches', 'create-branches', 'edit-branches', 'delete-branches',
            'view-violations', 'create-violations', 'edit-violations', 'delete-violations',
            'view-penalties', 'create-penalties', 'approve-penalties',
            'view-regulations', 'create-regulations', 'edit-regulations', 'delete-regulations',
            'view-activity-log',
            'manage-settings',
            'manage-users',
            'manage-roles',
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
            'view-penalties', 'create-penalties', 'approve-penalties',
            'view-regulations',
            'view-activity-log',
        ]);

        $teamLeaderRole = Role::create(['name' => 'team_leader', 'guard_name' => 'web']);
        $teamLeaderRole->givePermissionTo([
            'view-employees', 'view-teams', 'view-branches',
            'view-penalties', 'create-penalties',
        ]);

        $staffRole = Role::create(['name' => 'staff', 'guard_name' => 'web']);
        $staffRole->givePermissionTo([
            'view-employees', 'view-penalties',
        ]);

        // Assign admin role to admin user
        $admin->assignRole('admin');

        // 3. Demo branches
        $hn = Branch::create(['code' => 'BR-HN', 'name' => 'Chi nhánh Hà Nội', 'address' => '123 Nguyễn Huệ, Q.1', 'phone' => '024123456']);
        $hcm = Branch::create(['code' => 'BR-HCM', 'name' => 'Chi nhánh Hồ Chí Minh', 'address' => '456 Lê Lợi, Q.1', 'phone' => '028123456']);
        $dn = Branch::create(['code' => 'BR-DN', 'name' => 'Chi nhánh Đà Nẵng', 'address' => '789 Bạch Đằng, Hải Châu', 'phone' => '0236123456']);

        // 4. Demo teams
        $t1 = Team::create(['code' => 'TEAM-PB', 'name' => 'Pha chế', 'branch_id' => $hn->id]);
        $t2 = Team::create(['code' => 'TEAM-BP', 'name' => 'Bếp', 'branch_id' => $hn->id]);
        $t3 = Team::create(['code' => 'TEAM-SV', 'name' => 'Server', 'branch_id' => $hcm->id]);
        $t4 = Team::create(['code' => 'TEAM-BV', 'name' => 'Bảo vệ', 'branch_id' => $dn->id]);

        // 5. Demo employees
        $names = [
            ['Nguyễn Văn An', 'NV001', $t1, $hn, 'Pha chế trưởng'],
            ['Trần Thị Bình', 'NV002', $t1, $hn, 'Pha chế viên'],
            ['Lê Văn Cường', 'NV003', $t2, $hn, 'Bếp trưởng'],
            ['Phạm Thị Dung', 'NV004', $t2, $hn, 'Phụ bếp'],
            ['Hoàng Văn Em', 'NV005', $t3, $hcm, 'Server trưởng'],
            ['Ngô Thị Phương', 'NV006', $t3, $hcm, 'Server'],
            ['Đặng Văn Giang', 'NV007', $t3, $hcm, 'Server'],
            ['Vũ Thị Hạnh', 'NV008', $t4, $dn, 'Bảo vệ'],
            ['Bùi Văn Ý', 'NV009', $t4, $dn, 'Bảo vệ'],
            ['Đỗ Thị Kim', 'NV010', $t1, $hn, 'Pha chế viên'],
        ];

        $employees = [];
        foreach ($names as $i => [$name, $code, $team, $branch, $position]) {
            $employees[] = Employee::create([
                'code' => $code,
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)) . '@pcrm.vn',
                'phone' => '09' . str_pad((string)(10000000 + $i), 7, '0', STR_PAD_LEFT),
                'position' => $position,
                'branch_id' => $branch->id,
                'team_id' => $team->id,
                'is_active' => true,
                'joined_at' => now()->subMonths(rand(1, 12)),
            ]);
        }

        // 6. Regulations
        $reg1 = Regulation::create([
            'code' => 'REG-001',
            'name' => 'Quy chế giờ giấc làm việc',
            'type' => 'points',
            'default_points' => 10,
            'description' => 'Xử phạt đối với hành vi đi trễ, về sớm',
        ]);
        $reg2 = Regulation::create([
            'code' => 'REG-002',
            'name' => 'Quy chế trang phục',
            'type' => 'both',
            'default_points' => 5,
            'default_money' => 50000,
            'description' => 'Vi phạm quy định về đồng phục và trang phục làm việc',
        ]);
        $reg3 = Regulation::create([
            'code' => 'REG-003',
            'name' => 'Quy chế vệ sinh an toàn thực phẩm',
            'type' => 'points',
            'default_points' => 20,
            'description' => 'Vi phạm quy định vệ sinh trong khu vực bếp và pha chế',
        ]);
        $reg4 = Regulation::create([
            'code' => 'REG-004',
            'name' => 'Quy chế hành vi ứng xử',
            'type' => 'money',
            'default_money' => 200000,
            'description' => 'Vi phạm về thái độ phục vụ khách hàng',
        ]);
        $reg5 = Regulation::create([
            'code' => 'REG-005',
            'name' => 'Quy chế an toàn lao động',
            'type' => 'both',
            'default_points' => 15,
            'default_money' => 100000,
            'description' => 'Vi phạm quy định an toàn trong quá trình làm việc',
        ]);

        // 7. Violations
        Violation::create(['code' => 'VIO-DT01', 'name' => 'Đi trễ dưới 15 phút', 'severity' => 'low', 'regulation_id' => $reg1->id]);
        Violation::create(['code' => 'VIO-DT02', 'name' => 'Đi trễ 15-30 phút', 'severity' => 'medium', 'regulation_id' => $reg1->id]);
        Violation::create(['code' => 'VIO-DT03', 'name' => 'Đi trễ trên 30 phút', 'severity' => 'high', 'regulation_id' => $reg1->id]);
        Violation::create(['code' => 'VIO-TP01', 'name' => 'Không mặc đồng phục', 'severity' => 'medium', 'regulation_id' => $reg2->id]);
        Violation::create(['code' => 'VIO-TP02', 'name' => 'Mặc đồng phục không đúng quy định', 'severity' => 'low', 'regulation_id' => $reg2->id]);
        Violation::create(['code' => 'VIO-VS01', 'name' => 'Khu vực làm việc không vệ sinh', 'severity' => 'high', 'regulation_id' => $reg3->id]);
        Violation::create(['code' => 'VIO-HV01', 'name' => 'Thái độ không tốt với khách hàng', 'severity' => 'critical', 'regulation_id' => $reg4->id]);
        Violation::create(['code' => 'VIO-AT01', 'name' => 'Không sử dụng thiết bị bảo hộ', 'severity' => 'high', 'regulation_id' => $reg5->id]);

        // 8. Demo scores (random initial scores for each employee)
        foreach ($employees as $emp) {
            EmployeeScore::create([
                'employee_id' => $emp->id,
                'points' => rand(60, 120),
                'reason' => 'Điểm khởi tạo đầu tháng',
                'type' => 'reward',
            ]);
        }
    }
}
