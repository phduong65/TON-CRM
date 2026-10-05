<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Models\StaffRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Sinh dữ liệu mẫu cho hub "Yêu cầu và Phê duyệt" (Lượt chấm công/Công tác/Đi muộn về sớm/
 * Thay đổi giờ vào-ra/Tăng ca, Nghỉ phép, Đổi ca làm) ở đủ 3 trạng thái (pending/approved/rejected)
 * — dùng để test thủ công trên trình duyệt tính năng duyệt/từ chối/huỷ (chính chủ) và xoá
 * (quản lý có quyền delete-staff-requests/delete-leave-requests/delete-shift-swaps).
 *
 * Không tự động chạy trong DatabaseSeeder — chỉ seed thủ công khi cần dữ liệu test:
 * Usage: php artisan db:seed --class=RequestsSampleDataSeeder
 *
 * Idempotent: chạy lại nhiều lần không tạo trùng (dùng updateOrCreate theo "code"/user email).
 */
class RequestsSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::firstOrCreate(
            ['code' => 'BR-DEMO'],
            ['name' => 'Chi nhánh Demo Test', 'is_active' => true]
        );

        // firstOrCreate role 'staff' — seeder này phải tự chạy được kể cả trên DB mới migrate,
        // chưa qua DatabaseSeeder (roles/permissions chưa tồn tại).
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $reviewer = Role::where('name', 'admin')->where('guard_name', 'web')->exists()
            ? User::role('admin')->first()
            : User::first();

        $userA = User::updateOrCreate(
            ['email' => 'demo.requests.a@pcrm.local'],
            ['name' => 'Demo Nhân viên A', 'password' => Hash::make('password')]
        );
        if (!$userA->hasRole('staff')) {
            $userA->assignRole('staff');
        }
        $employeeA = Employee::updateOrCreate(
            ['code' => 'DEMO-REQ-A'],
            [
                'name' => 'Demo Nhân viên A', 'user_id' => $userA->id, 'branch_id' => $branch->id,
                'is_active' => true, 'employment_type' => 'full_time', 'is_office' => true,
            ]
        );

        $userB = User::updateOrCreate(
            ['email' => 'demo.requests.b@pcrm.local'],
            ['name' => 'Demo Nhân viên B', 'password' => Hash::make('password')]
        );
        if (!$userB->hasRole('staff')) {
            $userB->assignRole('staff');
        }
        $employeeB = Employee::updateOrCreate(
            ['code' => 'DEMO-REQ-B'],
            [
                'name' => 'Demo Nhân viên B', 'user_id' => $userB->id, 'branch_id' => $branch->id,
                'is_active' => true, 'employment_type' => 'full_time', 'is_office' => true,
            ]
        );

        $this->seedStaffRequests($employeeA, $reviewer);
        $this->seedLeaveRequests($employeeB, $reviewer);
        $this->seedShiftSwapRequests($employeeA, $employeeB, $branch, $reviewer);

        $this->command?->info('Đã seed dữ liệu mẫu cho hub Yêu cầu và Phê duyệt (2 nhân viên demo: DEMO-REQ-A, DEMO-REQ-B).');
    }

    /**
     * 5 loại dùng chung bảng staff_requests — mỗi loại 1 bản ghi, trải đều 3 trạng thái để
     * test đủ các nút hành động (duyệt/từ chối/huỷ/xoá) trên giao diện.
     */
    private function seedStaffRequests(Employee $employee, ?User $reviewer): void
    {
        $today = now()->toDateString();

        $rows = [
            [
                'code' => 'DEMO-ATC-0001', 'type' => 'attendance_correction', 'status' => 'pending',
                'payload' => ['check_in_at' => '08:10'], 'reason' => 'Quên chấm công buổi sáng',
            ],
            [
                'code' => 'DEMO-BTR-0001', 'type' => 'business_trip', 'status' => 'approved',
                'payload' => ['from_time' => '09:00', 'to_time' => '11:00', 'location' => 'Gặp khách quận 1'],
                'reason' => 'Gặp đối tác', 'reviewed_by' => $reviewer?->id, 'reviewed_at' => now(),
            ],
            [
                'code' => 'DEMO-LE-0001', 'type' => 'late_early', 'status' => 'rejected',
                'payload' => ['mode' => 'late', 'minutes' => 20], 'reason' => 'Kẹt xe',
                'reviewed_by' => $reviewer?->id, 'reviewed_at' => now(), 'rejection_reason' => 'Không có minh chứng',
            ],
            [
                'code' => 'DEMO-TC-0001', 'type' => 'time_change', 'status' => 'pending',
                'payload' => ['new_check_in' => '10:00', 'new_check_out' => '19:00'], 'reason' => 'Đưa con đi học',
            ],
            [
                'code' => 'DEMO-OT-0001', 'type' => 'overtime', 'status' => 'approved',
                'payload' => ['from_time' => '18:00', 'to_time' => '20:30'], 'reason' => 'Hỗ trợ sự kiện',
                'reviewed_by' => $reviewer?->id, 'reviewed_at' => now(),
            ],
        ];

        foreach ($rows as $row) {
            StaffRequest::updateOrCreate(
                ['code' => $row['code']],
                array_merge(['employee_id' => $employee->id, 'work_date' => $today], $row)
            );
        }
    }

    private function seedLeaveRequests(Employee $employee, ?User $reviewer): void
    {
        $rows = [
            [
                'code' => 'DEMO-LR-0001', 'status' => 'pending', 'type' => 'annual',
                'date_from' => now()->addDays(3)->toDateString(), 'date_to' => now()->addDays(4)->toDateString(),
                'reason' => 'Về quê giỗ tổ',
            ],
            [
                'code' => 'DEMO-LR-0002', 'status' => 'approved', 'type' => 'sick',
                'date_from' => now()->subDays(2)->toDateString(), 'date_to' => now()->subDays(2)->toDateString(),
                'reason' => 'Cảm sốt', 'reviewed_by' => $reviewer?->id, 'reviewed_at' => now(),
            ],
            [
                'code' => 'DEMO-LR-0003', 'status' => 'rejected', 'type' => 'unpaid',
                'date_from' => now()->addDays(10)->toDateString(), 'date_to' => now()->addDays(12)->toDateString(),
                'reason' => 'Việc riêng', 'reviewed_by' => $reviewer?->id, 'reviewed_at' => now(),
                'rejection_reason' => 'Trùng cao điểm cuối tháng',
            ],
        ];

        foreach ($rows as $row) {
            LeaveRequest::updateOrCreate(
                ['code' => $row['code']],
                array_merge(['employee_id' => $employee->id], $row)
            );
        }
    }

    /**
     * Đổi ca cần 2 ca đã xếp thật (tương lai, khác nhân viên) để tham chiếu — tạo 1 mẫu Shift
     * dùng chung nếu chưa có, rồi xếp ca cho A/B vào 2 ngày khác nhau trước khi tạo yêu cầu đổi.
     */
    private function seedShiftSwapRequests(Employee $employeeA, Employee $employeeB, Branch $branch, ?User $reviewer): void
    {
        $shift = Shift::firstOrCreate(
            ['code' => 'CA-DEMO'],
            [
                'name' => 'Ca Demo', 'start_time' => '09:00', 'end_time' => '18:00',
                'work_mode' => 'onsite', 'break_minutes' => 60, 'branch_id' => null, 'is_active' => true,
            ]
        );

        $scheduleA = ShiftSchedule::updateOrCreate(
            ['employee_id' => $employeeA->id, 'work_date' => now()->addDays(5)->toDateString()],
            ['shift_id' => $shift->id, 'branch_id' => $branch->id, 'status' => 'scheduled', 'assignment_type' => 'rotation']
        );
        $scheduleB = ShiftSchedule::updateOrCreate(
            ['employee_id' => $employeeB->id, 'work_date' => now()->addDays(6)->toDateString()],
            ['shift_id' => $shift->id, 'branch_id' => $branch->id, 'status' => 'scheduled', 'assignment_type' => 'rotation']
        );

        ShiftSwapRequest::updateOrCreate(
            ['code' => 'DEMO-SWP-0001'],
            [
                'requester_employee_id' => $employeeA->id, 'requester_schedule_id' => $scheduleA->id,
                'target_employee_id' => $employeeB->id, 'target_schedule_id' => $scheduleB->id,
                'reason' => 'Đổi lịch cá nhân', 'status' => 'pending',
            ]
        );

        $scheduleA2 = ShiftSchedule::updateOrCreate(
            ['employee_id' => $employeeA->id, 'work_date' => now()->addDays(7)->toDateString()],
            ['shift_id' => $shift->id, 'branch_id' => $branch->id, 'status' => 'scheduled', 'assignment_type' => 'rotation']
        );
        $scheduleB2 = ShiftSchedule::updateOrCreate(
            ['employee_id' => $employeeB->id, 'work_date' => now()->addDays(8)->toDateString()],
            ['shift_id' => $shift->id, 'branch_id' => $branch->id, 'status' => 'scheduled', 'assignment_type' => 'rotation']
        );

        ShiftSwapRequest::updateOrCreate(
            ['code' => 'DEMO-SWP-0002'],
            [
                'requester_employee_id' => $employeeA->id, 'requester_schedule_id' => $scheduleA2->id,
                'target_employee_id' => $employeeB->id, 'target_schedule_id' => $scheduleB2->id,
                'reason' => 'Bận việc gia đình', 'status' => 'rejected',
                'reviewed_by' => $reviewer?->id, 'reviewed_at' => now(), 'rejection_reason' => 'Đã quá gần ngày làm việc',
            ]
        );
    }
}
