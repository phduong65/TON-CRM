<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Team;
use App\Models\Employee;
use App\Models\EmployeeScore;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder nhân viên thực tế từ file Danh_Sach_Nhan_Vien.xlsx
 * Usage: php artisan db:seed --class=RealEmployeeSeeder
 */
class RealEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tạo chi nhánh thực tế
        $branches = [
            'CN Phường An Khánh' => Branch::firstOrCreate(
                ['code' => 'BR-AK'],
                ['name' => 'CN Phường An Khánh', 'address' => 'Phường An Khánh, TP. Thủ Đức, HCM', 'phone' => '0900000001']
            ),
            'CN Phường Sài Gòn' => Branch::firstOrCreate(
                ['code' => 'BR-SG'],
                ['name' => 'CN Phường Sài Gòn', 'address' => 'Phường Sài Gòn, HCM', 'phone' => '0900000002']
            ),
            'CN_44_Nguyen_hue' => Branch::firstOrCreate(
                ['code' => 'BR-NH44'],
                ['name' => 'CN 44 Nguyễn Huệ', 'address' => '44 Nguyễn Huệ, Q.1, HCM', 'phone' => '0900000003']
            ),
        ];

        // 2. Tạo team theo phòng ban + chi nhánh
        $teamMap = [];
        $teamDefs = [
            'Văn Phòng|CN Phường An Khánh' => ['code' => 'TEAM-VP-AK', 'name' => 'Văn Phòng', 'branch' => 'CN Phường An Khánh'],
            'Nhà hàng|CN Phường Sài Gòn'   => ['code' => 'TEAM-NH-SG', 'name' => 'Nhà hàng', 'branch' => 'CN Phường Sài Gòn'],
            'Bếp|CN Phường Sài Gòn'         => ['code' => 'TEAM-BP-SG', 'name' => 'Bếp', 'branch' => 'CN Phường Sài Gòn'],
            'Bar|CN Phường Sài Gòn'          => ['code' => 'TEAM-BR-SG', 'name' => 'Bar', 'branch' => 'CN Phường Sài Gòn'],
            'Quản lý|CN Phường Sài Gòn'      => ['code' => 'TEAM-QL-SG', 'name' => 'Quản lý', 'branch' => 'CN Phường Sài Gòn'],
            'SPA|CN_44_Nguyen_hue'           => ['code' => 'TEAM-SPA-NH', 'name' => 'SPA', 'branch' => 'CN_44_Nguyen_hue'],
        ];

        foreach ($teamDefs as $key => $def) {
            $teamMap[$key] = Team::firstOrCreate(
                ['code' => $def['code']],
                ['name' => $def['name'], 'branch_id' => $branches[$def['branch']]->id]
            );
        }

        // 3. Danh sách nhân viên từ Excel
        $employees = [
            ['code' => '0001',         'name' => 'Chị Bích',                'phone' => '+84902497984', 'gender' => 'Nam',            'branch' => 'CN Phường An Khánh', 'dept' => 'Văn Phòng',  'position' => '',                    'access' => 'Quản lý'],
            ['code' => 'GD-TVTON',     'name' => 'Trần Văn Tôn',           'phone' => '+84775070993', 'gender' => 'Nam',            'branch' => 'CN Phường An Khánh', 'dept' => 'Văn Phòng',  'position' => 'Giám đốc',            'access' => 'Quản lý'],
            ['code' => 'NV-NDMHIEN',   'name' => 'Nguyễn Đình Minh Hiển',  'phone' => '+84338318541', 'gender' => 'Nam',            'branch' => 'CN Phường Sài Gòn',  'dept' => 'Nhà hàng',   'position' => 'Run Food',            'access' => 'Nhân viên'],
            ['code' => 'NV-THPHUONG',  'name' => 'Trần Hữu Phương',        'phone' => '+84909761175', 'gender' => 'Nam',            'branch' => 'CN Phường Sài Gòn',  'dept' => 'Bếp',        'position' => 'Đầu bếp',             'access' => 'Nhân viên'],
            ['code' => 'NV_TTLE',      'name' => 'Trần Trung Lễ',          'phone' => '+84337888385', 'gender' => 'Nam',            'branch' => 'CN Phường Sài Gòn',  'dept' => 'Bếp',        'position' => 'Bếp trưởng',          'access' => 'Nhân viên'],
            ['code' => 'NV_TDTRI',     'name' => 'Từ Duy Trí',             'phone' => '+84989834340', 'gender' => 'Nam',            'branch' => 'CN Phường Sài Gòn',  'dept' => 'Bếp',        'position' => 'Đầu bếp',             'access' => 'Nhân viên'],
            ['code' => 'NV_DVQUANG',   'name' => 'Đỗ Vinh Quang',          'phone' => '+84934620528', 'gender' => 'Nam',            'branch' => 'CN Phường Sài Gòn',  'dept' => 'Bar',        'position' => 'Bartender',           'access' => 'Nhân viên'],
            ['code' => 'NV_QTNHAT',    'name' => 'Quách Tiểu Nhật',        'phone' => '+84908167104', 'gender' => 'Nam',            'branch' => 'CN Phường Sài Gòn',  'dept' => 'Bar',        'position' => 'Bartender',           'access' => 'Nhân viên'],
            ['code' => 'NV_TTCLE',     'name' => 'Trần Thị Cẩm Lệ',       'phone' => '+84792191648', 'gender' => 'Nữ',             'branch' => 'CN Phường Sài Gòn',  'dept' => 'Quản lý',    'position' => 'Quản lý nhà hàng',    'access' => 'Quản Lý Chi Nhánh'],
            ['code' => 'NV-NTVY',      'name' => 'Nguyễn Tường Vy',        'phone' => '+84836202608', 'gender' => 'Nữ',             'branch' => 'CN Phường Sài Gòn',  'dept' => 'Nhà hàng',   'position' => 'Barista',             'access' => 'Nhân viên'],
            ['code' => 'NV-NTPDUNG',   'name' => 'Nguyễn Thị Phương Dung', 'phone' => '+84966253445', 'gender' => 'Nữ',             'branch' => 'CN Phường An Khánh', 'dept' => 'Văn Phòng',  'position' => 'Kế toán',             'access' => 'Quản lý'],
            ['code' => 'NV_NPHUY',     'name' => 'Nguyễn Phúc Huy',        'phone' => '+84862639931', 'gender' => 'Nam',            'branch' => 'CN Phường An Khánh', 'dept' => 'Văn Phòng',  'position' => 'Thu mua',             'access' => 'Nhân viên'],
            ['code' => 'NV_DQBINH',    'name' => 'Đặng Quốc Bình',         'phone' => '+84387956644', 'gender' => 'Nam',            'branch' => 'CN Phường An Khánh', 'dept' => 'Văn Phòng',  'position' => 'IT',                  'access' => 'Nhân viên'],
            ['code' => 'NV-NTHHANH',   'name' => 'Nguyễn Thị Hồng Hạnh',   'phone' => '+84565942206', 'gender' => 'Nữ',             'branch' => 'CN Phường An Khánh', 'dept' => 'Văn Phòng',  'position' => 'Marketing',           'access' => 'Nhân viên'],
            ['code' => '0002',         'name' => 'Trần Thị Thúy',          'phone' => '+84938182990', 'gender' => 'Nữ',             'branch' => 'CN Phường Sài Gòn',  'dept' => 'Bếp',        'position' => 'Phụ Bếp Chiều',       'access' => 'Nhân viên'],
            ['code' => 'NV_LVVang',    'name' => 'Lê Văn Vàng',            'phone' => '+84346466917', 'gender' => 'Không xác định', 'branch' => 'CN Phường Sài Gòn',  'dept' => 'Nhà hàng',   'position' => 'Phục vụ',             'access' => 'Nhân viên'],
            ['code' => 'NV_NHATU',     'name' => 'Nguyễn Hoàng Anh Tú',    'phone' => '+84901857200', 'gender' => 'Không xác định', 'branch' => 'CN Phường Sài Gòn',  'dept' => 'Bếp',        'position' => 'Đầu bếp',             'access' => 'Nhân viên'],
            ['code' => 'NV_NHNMAI',    'name' => 'Nguyễn Huỳnh Ngọc Mai',  'phone' => '+84857238682', 'gender' => 'Nữ',             'branch' => 'CN_44_Nguyen_hue',   'dept' => 'SPA',        'position' => 'NV SPA',              'access' => 'Nhân viên'],
            ['code' => 'PDUONG',       'name' => 'Phạm Dương',             'phone' => '+84336719208', 'gender' => 'Không xác định', 'branch' => 'CN Phường An Khánh', 'dept' => 'Văn Phòng',  'position' => 'IT',                  'access' => 'Nhân viên'],
        ];

        // Map nhóm truy cập → role
        $roleMap = [
            'Nhân viên'          => 'staff',
            'Quản lý'            => 'manager',
            'Quản Lý Chi Nhánh'  => 'manager',
        ];

        $defaultPassword = Hash::make('nhanvien@123');

        foreach ($employees as $emp) {
            $teamKey = $emp['dept'] . '|' . $emp['branch'];
            $branch = $branches[$emp['branch']] ?? null;
            $team = $teamMap[$teamKey] ?? null;

            // Tạo email từ mã nhân viên
            $emailSlug = Str::lower(Str::replace([' ', '+'], '', $emp['code']));
            $email = $emailSlug . '@hr.vn';

            // Tạo User account
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $emp['name'],
                    'password' => $defaultPassword,
                ]
            );

            // Gán role
            $role = $roleMap[$emp['access']] ?? 'staff';
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }

            // Tạo Employee record
            $employee = Employee::firstOrCreate(
                ['code' => $emp['code']],
                [
                    'user_id' => $user->id,
                    'name' => $emp['name'],
                    'email' => $email,
                    'phone' => $emp['phone'],
                    'position' => $emp['position'],
                    'branch_id' => $branch?->id,
                    'team_id' => $team?->id,
                    'is_active' => true,
                    'joined_at' => now(),
                ]
            );

            // Tạo điểm khởi tạo
            if ($employee->wasRecentlyCreated) {
                EmployeeScore::create([
                    'employee_id' => $employee->id,
                    'points' => 100,
                    'reason' => 'Điểm khởi tạo đầu tháng',
                    'type' => 'reward',
                ]);
            }
        }
    }
}
