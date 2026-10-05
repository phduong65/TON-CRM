<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeReport;
use App\Models\Penalty;
use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\RewardType;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Sinh dữ liệu mẫu cho Phiếu phạt (Penalty), Phiếu thưởng (Reward), Báo cáo chéo (EmployeeReport)
 * ở đủ 3 trạng thái (pending/approved/rejected) — dùng để test thủ công tính năng xoá và xác
 * nhận không còn bị trùng "code" sau khi sửa Penalty::nextCode()/withTrashed() (xem
 * PenaltiesController::store(), RewardsController::store(), EmployeeReportsController::store()).
 *
 * Không tự động chạy trong DatabaseSeeder — chỉ seed thủ công khi cần dữ liệu test:
 * Usage: php artisan db:seed --class=PenaltyRewardReportSampleDataSeeder
 *
 * Idempotent: chạy lại nhiều lần không tạo trùng (dùng updateOrCreate theo "code"/email).
 */
class PenaltyRewardReportSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::firstOrCreate(
            ['code' => 'BR-DEMO'],
            ['name' => 'Chi nhánh Demo Test', 'is_active' => true]
        );

        $reviewer = Role::where('name', 'admin')->where('guard_name', 'web')->exists()
            ? User::role('admin')->first()
            : User::first();

        $userA = User::updateOrCreate(
            ['email' => 'demo.pnl.a@pcrm.local'],
            ['name' => 'Demo Nhân viên C', 'password' => Hash::make('password')]
        );
        $employeeA = Employee::updateOrCreate(
            ['code' => 'DEMO-PNL-A'],
            ['name' => 'Demo Nhân viên C', 'user_id' => $userA->id, 'branch_id' => $branch->id, 'is_active' => true]
        );

        $userB = User::updateOrCreate(
            ['email' => 'demo.pnl.b@pcrm.local'],
            ['name' => 'Demo Nhân viên D', 'password' => Hash::make('password')]
        );
        $employeeB = Employee::updateOrCreate(
            ['code' => 'DEMO-PNL-B'],
            ['name' => 'Demo Nhân viên D', 'user_id' => $userB->id, 'branch_id' => $branch->id, 'is_active' => true]
        );

        $this->seedPenalties($employeeA, $reviewer);
        $this->seedRewards($employeeA, $reviewer);
        $this->seedReports($employeeA, $employeeB, $reviewer);

        $this->command?->info('Đã seed dữ liệu mẫu Phiếu phạt/Phiếu thưởng/Báo cáo chéo (2 nhân viên demo: DEMO-PNL-A, DEMO-PNL-B).');
    }

    private function seedPenalties(Employee $employee, ?User $reviewer): void
    {
        $violation = Violation::firstOrCreate(
            ['name' => 'Demo - Vi phạm test'],
            ['penalty_type' => 'points', 'points_deducted' => 10, 'money_deducted' => 0, 'is_active' => true]
        );

        $rows = [
            ['code' => 'DEMO-PNL-0001', 'status' => 'pending'],
            [
                'code' => 'DEMO-PNL-0002', 'status' => 'approved',
                'approved_by' => $reviewer?->id, 'approved_at' => now(),
            ],
            [
                'code' => 'DEMO-PNL-0003', 'status' => 'rejected',
                'rejected_reason' => 'Không đủ minh chứng',
            ],
        ];

        foreach ($rows as $row) {
            Penalty::updateOrCreate(
                ['code' => $row['code']],
                array_merge([
                    'employee_id' => $employee->id, 'violation_id' => $violation->id,
                    'created_by' => $reviewer?->id, 'description' => 'Vi phạm mẫu để test',
                    'total_points_deducted' => $violation->points_deducted, 'total_money_deducted' => 0,
                ], $row)
            );
        }
    }

    private function seedRewards(Employee $employee, ?User $reviewer): void
    {
        $category = RewardCategory::firstOrCreate(
            ['name' => 'Demo - Khen thưởng test'],
            ['is_active' => true, 'created_by' => $reviewer?->id]
        );
        $rewardType = RewardType::firstOrCreate(
            ['reward_category_id' => $category->id, 'name' => 'Demo - Nhân viên xuất sắc'],
            ['default_points' => 15, 'is_active' => true, 'created_by' => $reviewer?->id]
        );

        $rows = [
            ['code' => 'DEMO-RWD-0001', 'status' => 'pending'],
            [
                'code' => 'DEMO-RWD-0002', 'status' => 'approved',
                'approved_by' => $reviewer?->id, 'approved_at' => now(),
            ],
            [
                'code' => 'DEMO-RWD-0003', 'status' => 'rejected',
                'rejected_reason' => 'Chưa đủ tiêu chí',
            ],
        ];

        foreach ($rows as $row) {
            Reward::updateOrCreate(
                ['code' => $row['code']],
                array_merge([
                    'target_type' => 'individual', 'employee_id' => $employee->id,
                    'reward_type_id' => $rewardType->id, 'created_by' => $reviewer?->id,
                    'description' => 'Khen thưởng mẫu để test', 'total_points_awarded' => $rewardType->default_points,
                ], $row)
            );
        }
    }

    private function seedReports(Employee $reporter, Employee $reported, ?User $reviewer): void
    {
        $rows = [
            ['code' => 'DEMO-RPT-0001', 'status' => 'pending'],
            [
                'code' => 'DEMO-RPT-0002', 'status' => 'approved',
                'reviewed_by' => $reviewer?->id, 'reviewed_at' => now(),
            ],
            [
                'code' => 'DEMO-RPT-0003', 'status' => 'rejected',
                'reviewed_by' => $reviewer?->id, 'reviewed_at' => now(),
                'rejection_reason' => 'Không xác thực được sự việc',
            ],
        ];

        foreach ($rows as $row) {
            EmployeeReport::updateOrCreate(
                ['code' => $row['code']],
                array_merge([
                    'reporter_employee_id' => $reporter->id, 'reported_employee_id' => $reported->id,
                    'type' => 'individual', 'description' => 'Báo cáo mẫu để test',
                    'reward_points' => 5, 'created_by' => $reporter->user_id,
                ], $row)
            );
        }
    }
}
