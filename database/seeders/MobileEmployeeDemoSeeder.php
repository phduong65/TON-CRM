<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MonthlyEmployeeScore;
use App\Models\Notification;
use App\Models\Penalty;
use App\Models\Position;
use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\RewardType;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Models\StaffRequest;
use App\Models\User;
use App\Models\Violation;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Dữ liệu demo đầy đủ cho tài khoản mobile nhân viên NV-NDMHIEN.
 *
 * Usage:
 *   php artisan db:seed --class=MobileEmployeeDemoSeeder
 *
 * Seeder idempotent: có thể chạy lại nhiều lần mà không sinh mã hoặc lịch trùng.
 */
class MobileEmployeeDemoSeeder extends Seeder
{
    private const EMPLOYEE_CODE = 'NV-NDMHIEN';

    public function run(): void
    {
        $employee = Employee::with(['user', 'branch', 'team'])
            ->where('code', self::EMPLOYEE_CODE)
            ->first();

        if (! $employee || ! $employee->user) {
            throw new \RuntimeException(
                'Không tìm thấy nhân viên NV-NDMHIEN hoặc tài khoản liên kết. '
                .'Hãy chạy RealEmployeeSeeder trước.'
            );
        }

        DB::transaction(function () use ($employee): void {
            $reviewer = $this->reviewer($employee->user);
            $colleague = $this->colleague($employee);
            $shifts = $this->seedShifts($employee);

            $this->seedSchedulesAndAttendance($employee, $reviewer, $shifts);
            $this->seedMonthlyScores($employee);

            [$penalties, $rewards] = $this->seedPenaltiesAndRewards(
                $employee,
                $reviewer
            );

            $requests = $this->seedRequests(
                $employee,
                $colleague,
                $reviewer,
                $shifts['evening']
            );

            $this->seedNotifications(
                $employee->user,
                $reviewer,
                $penalties,
                $rewards,
                $requests
            );
        });

        $this->command?->info(
            'Đã seed dữ liệu mobile đầy đủ cho NV-NDMHIEN: lịch làm, chấm công, '
            .'điểm, phạt/thưởng, yêu cầu và thông báo.'
        );
    }

    private function reviewer(User $fallback): User
    {
        return User::role('admin')->first()
            ?? User::role('manager')->first()
            ?? $fallback;
    }

    private function colleague(Employee $employee): Employee
    {
        $existing = Employee::with('user')
            ->whereKeyNot($employee->id)
            ->where('branch_id', $employee->branch_id)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->first();

        if ($existing) {
            return $existing;
        }

        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $user = User::updateOrCreate(
            ['email' => 'mobile.demo.colleague@pcrm.local'],
            [
                'name' => 'Đồng nghiệp Demo',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        if (! $user->hasRole('staff')) {
            $user->assignRole('staff');
        }

        $position = Position::firstOrCreate(['name' => 'Phục vụ'], ['is_active' => true]);

        return Employee::updateOrCreate(
            ['code' => 'MOBILE-DEMO-COLLEAGUE'],
            [
                'user_id' => $user->id,
                'name' => 'Đồng nghiệp Demo',
                'email' => $user->email,
                'position_id' => $position->id,
                'branch_id' => $employee->branch_id,
                'team_id' => $employee->team_id,
                'is_active' => true,
                'employment_type' => 'full_time',
                'is_office' => false,
                'joined_at' => now()->subYear(),
            ]
        );
    }

    /**
     * @return array{morning: Shift, evening: Shift, wfh: Shift}
     */
    private function seedShifts(Employee $employee): array
    {
        $common = [
            'branch_id' => $employee->branch_id,
            'break_minutes' => 60,
            'grace_late_minutes' => 5,
            'grace_early_minutes' => 5,
            'standard_work_hours' => 8,
            'shift_type' => 'fulltime',
            'is_active' => true,
        ];

        return [
            'morning' => Shift::updateOrCreate(
                ['code' => 'MOB-SANG'],
                array_merge($common, [
                    'name' => 'Ca sáng',
                    'start_time' => '08:00',
                    'end_time' => '17:00',
                    'is_overnight' => false,
                    'work_mode' => 'onsite',
                    'color' => '#1769E0',
                ])
            ),
            'evening' => Shift::updateOrCreate(
                ['code' => 'MOB-CHIEU'],
                array_merge($common, [
                    'name' => 'Ca chiều',
                    'start_time' => '14:00',
                    'end_time' => '23:00',
                    'is_overnight' => false,
                    'work_mode' => 'onsite',
                    'color' => '#E56A54',
                ])
            ),
            'wfh' => Shift::updateOrCreate(
                ['code' => 'MOB-WFH'],
                array_merge($common, [
                    'name' => 'Ca làm việc từ xa',
                    'start_time' => '08:30',
                    'end_time' => '17:30',
                    'is_overnight' => false,
                    'work_mode' => 'wfh',
                    'color' => '#34B7A7',
                ])
            ),
        ];
    }

    /**
     * @param  array{morning: Shift, evening: Shift, wfh: Shift}  $shifts
     */
    private function seedSchedulesAndAttendance(
        Employee $employee,
        User $reviewer,
        array $shifts
    ): void {
        $today = now()->startOfDay();

        for ($offset = -24; $offset <= 20; $offset++) {
            $date = $today->copy()->addDays($offset);

            if ($date->isSunday() && $offset !== 0) {
                continue;
            }

            $shift = match (true) {
                $offset === 0 => $shifts['wfh'],
                $date->dayOfWeek === Carbon::SATURDAY => $shifts['evening'],
                default => $shifts['morning'],
            };

            $schedule = ShiftSchedule::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'work_date' => $date->toDateString(),
                ],
                [
                    'shift_id' => $shift->id,
                    'branch_id' => $employee->branch_id,
                    'assignment_type' => $offset < 0 ? 'fixed' : 'rotation',
                    'status' => 'scheduled',
                    'note' => $offset === 0
                        ? 'Ca WFH demo để có thể kiểm tra check-in không cần GPS/WiFi.'
                        : null,
                    'assigned_by' => $reviewer->id,
                ]
            );

            // Giữ hôm nay chưa chấm công để người dùng có thể thử check-in/out thật.
            if ($offset >= 0 || $offset % 6 === 0) {
                continue;
            }

            $lateMinutes = abs($offset) % 5 === 0 ? 12 : 0;
            $earlyMinutes = abs($offset) % 7 === 0 ? 15 : 0;
            $checkIn = Carbon::parse(
                $date->toDateString().' '.$shift->start_time
            )->addMinutes($lateMinutes);
            $checkOut = Carbon::parse(
                $date->toDateString().' '.$shift->end_time
            )->subMinutes($earlyMinutes);

            AttendanceLog::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'work_date' => $date->toDateString(),
                ],
                array_merge(
                    [
                        'shift_schedule_id' => $schedule->id,
                        'check_in_at' => $checkIn,
                        'check_out_at' => $checkOut,
                        'check_in_method' => $shift->isWfh() ? 'wfh' : 'gps_ip',
                        'check_out_method' => $shift->isWfh() ? 'wfh' : 'gps_ip',
                        'check_in_ip' => '203.0.113.10',
                        'check_out_ip' => '203.0.113.10',
                        'check_in_device' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5)',
                        'check_out_device' => abs($offset) % 9 === 0
                            ? 'Mozilla/5.0 (Linux; Android 14; Pixel 8)'
                            : 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5)',
                        'late_minutes' => $lateMinutes,
                        'early_minutes' => $earlyMinutes,
                        'full_credit' => $lateMinutes === 0 && $earlyMinutes === 0,
                        'overtime_hours' => abs($offset) % 8 === 0 ? 1.5 : 0,
                    ],
                    $schedule->shiftSnapshotAttributes()
                )
            );
        }
    }

    private function seedMonthlyScores(Employee $employee): void
    {
        $samples = [
            0 => [12, 5, 0, 93],
            1 => [6, 8, 2, 100],
            2 => [18, 4, 0, 86],
            3 => [9, 3, 0, 94],
            4 => [22, 7, 0, 85],
            5 => [4, 6, 2, 100],
        ];

        foreach ($samples as $monthsAgo => [$deducted, $rewarded, $surplus, $final]) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);

            MonthlyEmployeeScore::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'month' => $month->month,
                    'year' => $month->year,
                ],
                [
                    'initial_score' => 100,
                    'deducted_points' => $deducted,
                    'rewarded_points' => $rewarded,
                    'surplus_points' => $surplus,
                    'final_score' => $final,
                    'zone' => MonthlyEmployeeScore::computeZone($final),
                ]
            );
        }
    }

    /**
     * @return array{0: array<string, Penalty>, 1: array<string, Reward>}
     */
    private function seedPenaltiesAndRewards(
        Employee $employee,
        User $reviewer
    ): array {
        $violation = Violation::firstOrCreate(
            ['name' => 'Đi trễ không báo trước - Mobile demo'],
            [
                'description' => 'Dữ liệu mẫu hiển thị trên ứng dụng nhân viên.',
                'severity' => 'medium',
                'penalty_type' => 'points',
                'points_deducted' => 12,
                'money_deducted' => 0,
                'is_active' => true,
            ]
        );

        $penaltyRows = [
            'approved' => [
                'code' => 'MOB-NDM-PNL-001',
                'status' => 'approved',
                'approved_by' => $reviewer->id,
                'approved_at' => now()->subDays(8),
                'description' => 'Đi trễ 12 phút trong ca sáng.',
            ],
            'pending' => [
                'code' => 'MOB-NDM-PNL-002',
                'status' => 'pending',
                'description' => 'Quên xác nhận bàn giao cuối ca.',
            ],
            'rejected' => [
                'code' => 'MOB-NDM-PNL-003',
                'status' => 'rejected',
                'rejected_reason' => 'Đã xác minh do hệ thống chấm công lỗi.',
                'description' => 'Thiếu lượt check-out.',
            ],
        ];

        $penalties = [];
        foreach ($penaltyRows as $key => $row) {
            $penalties[$key] = Penalty::updateOrCreate(
                ['code' => $row['code']],
                array_merge([
                    'created_by' => $reviewer->id,
                    'employee_id' => $employee->id,
                    'violation_id' => $violation->id,
                    'total_points_deducted' => $row['status'] === 'approved'
                        ? 12
                        : 5,
                    'total_money_deducted' => 0,
                ], $row)
            );
        }

        $category = RewardCategory::firstOrCreate(
            ['name' => 'Khen thưởng vận hành - Mobile demo'],
            ['is_active' => true, 'created_by' => $reviewer->id]
        );
        $rewardType = RewardType::firstOrCreate(
            [
                'reward_category_id' => $category->id,
                'name' => 'Hỗ trợ ca cao điểm',
            ],
            [
                'description' => 'Chủ động hỗ trợ đồng đội trong giờ cao điểm.',
                'default_points' => 5,
                'is_active' => true,
                'created_by' => $reviewer->id,
            ]
        );

        $rewardRows = [
            'approved' => [
                'code' => 'MOB-NDM-RWD-001',
                'status' => 'approved',
                'approved_by' => $reviewer->id,
                'approved_at' => now()->subDays(4),
                'description' => 'Hỗ trợ ca tối cuối tuần.',
            ],
            'pending' => [
                'code' => 'MOB-NDM-RWD-002',
                'status' => 'pending',
                'description' => 'Được khách hàng khen ngợi.',
            ],
            'rejected' => [
                'code' => 'MOB-NDM-RWD-003',
                'status' => 'rejected',
                'rejected_reason' => 'Nội dung trùng phiếu thưởng đã duyệt.',
                'description' => 'Đề xuất thưởng hỗ trợ sự kiện.',
            ],
        ];

        $rewards = [];
        foreach ($rewardRows as $key => $row) {
            $rewards[$key] = Reward::updateOrCreate(
                ['code' => $row['code']],
                array_merge([
                    'target_type' => 'individual',
                    'employee_id' => $employee->id,
                    'reward_type_id' => $rewardType->id,
                    'created_by' => $reviewer->id,
                    'total_points_awarded' => 5,
                ], $row)
            );
        }

        return [$penalties, $rewards];
    }

    /**
     * @return array{
     *   leave: array<string, LeaveRequest>,
     *   swap: array<string, ShiftSwapRequest>,
     *   staff: array<string, StaffRequest>
     * }
     */
    private function seedRequests(
        Employee $employee,
        Employee $colleague,
        User $reviewer,
        Shift $shift
    ): array {
        $leaveRows = [
            'pending' => [
                'code' => 'MOB-NDM-LV-001',
                'date_from' => now()->addDays(12)->toDateString(),
                'date_to' => now()->addDays(13)->toDateString(),
                'type' => 'unpaid',
                'reason' => 'Giải quyết việc gia đình.',
                'status' => 'pending',
            ],
            'approved' => [
                'code' => 'MOB-NDM-LV-002',
                'date_from' => now()->subDays(18)->toDateString(),
                'date_to' => now()->subDays(18)->toDateString(),
                'type' => 'annual',
                'reason' => 'Nghỉ phép năm đã được duyệt.',
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now()->subDays(20),
            ],
            'rejected' => [
                'code' => 'MOB-NDM-LV-003',
                'date_from' => now()->addDays(4)->toDateString(),
                'date_to' => now()->addDays(4)->toDateString(),
                'type' => 'unpaid',
                'reason' => 'Việc cá nhân.',
                'status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now()->subDay(),
                'rejection_reason' => 'Trùng lịch sự kiện tại chi nhánh.',
            ],
        ];

        $leaves = [];
        foreach ($leaveRows as $key => $row) {
            $leaves[$key] = LeaveRequest::updateOrCreate(
                ['code' => $row['code']],
                array_merge(['employee_id' => $employee->id], $row)
            );
        }

        $swapDates = [
            now()->addDays(16)->startOfDay(),
            now()->addDays(17)->startOfDay(),
            now()->addDays(19)->startOfDay(),
            now()->addDays(20)->startOfDay(),
        ];

        $requesterScheduleA = $this->scheduleForSwap(
            $employee,
            $shift,
            $reviewer,
            $swapDates[0]
        );
        $targetScheduleA = $this->scheduleForSwap(
            $colleague,
            $shift,
            $reviewer,
            $swapDates[1]
        );
        $requesterScheduleB = $this->scheduleForSwap(
            $employee,
            $shift,
            $reviewer,
            $swapDates[2]
        );
        $targetScheduleB = $this->scheduleForSwap(
            $colleague,
            $shift,
            $reviewer,
            $swapDates[3]
        );

        $swaps = [
            'pending' => ShiftSwapRequest::updateOrCreate(
                ['code' => 'MOB-NDM-SWP-001'],
                [
                    'requester_employee_id' => $employee->id,
                    'requester_schedule_id' => $requesterScheduleA->id,
                    'target_employee_id' => $colleague->id,
                    'target_schedule_id' => $targetScheduleA->id,
                    'reason' => 'Cần đổi lịch cá nhân.',
                    'status' => 'pending',
                ]
            ),
            'rejected' => ShiftSwapRequest::updateOrCreate(
                ['code' => 'MOB-NDM-SWP-002'],
                [
                    'requester_employee_id' => $employee->id,
                    'requester_schedule_id' => $requesterScheduleB->id,
                    'target_employee_id' => $colleague->id,
                    'target_schedule_id' => $targetScheduleB->id,
                    'reason' => 'Đổi sang ca muộn hơn.',
                    'status' => 'rejected',
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now()->subDays(2),
                    'rejection_reason' => 'Ca mục tiêu đã đủ nhân sự.',
                ]
            ),
        ];

        $staffRows = [
            'attendance' => [
                'code' => 'MOB-NDM-STF-001',
                'type' => 'attendance_correction',
                'work_date' => now()->subDays(3)->toDateString(),
                'payload' => ['check_in_at' => '08:04', 'check_out_at' => '17:08'],
                'reason' => 'Thiết bị không nhận lượt check-in.',
                'status' => 'pending',
            ],
            'business_trip' => [
                'code' => 'MOB-NDM-STF-002',
                'type' => 'business_trip',
                'work_date' => now()->subDays(9)->toDateString(),
                'payload' => [
                    'from_time' => '10:00',
                    'to_time' => '12:30',
                    'location' => 'Nhà cung cấp Quận 1',
                ],
                'reason' => 'Nhận hàng gấp cho chi nhánh.',
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now()->subDays(8),
            ],
            'late_early' => [
                'code' => 'MOB-NDM-STF-003',
                'type' => 'late_early',
                'work_date' => now()->addDays(2)->toDateString(),
                'payload' => ['mode' => 'early', 'minutes' => 30],
                'reason' => 'Khám sức khỏe định kỳ.',
                'status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now()->subDay(),
                'rejection_reason' => 'Thiếu người thay ca.',
            ],
            'time_change' => [
                'code' => 'MOB-NDM-STF-004',
                'type' => 'time_change',
                'work_date' => now()->addDays(5)->toDateString(),
                'payload' => ['new_check_in' => '09:00', 'new_check_out' => '18:00'],
                'reason' => 'Điều chỉnh theo lịch giao hàng.',
                'status' => 'pending',
            ],
            'overtime' => [
                'code' => 'MOB-NDM-STF-005',
                'type' => 'overtime',
                'work_date' => now()->subDays(6)->toDateString(),
                'payload' => ['from_time' => '22:00', 'to_time' => '00:30'],
                'reason' => 'Hỗ trợ kiểm kê cuối ngày.',
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now()->subDays(5),
            ],
        ];

        $staffRequests = [];
        foreach ($staffRows as $key => $row) {
            $staffRequests[$key] = StaffRequest::updateOrCreate(
                ['code' => $row['code']],
                array_merge(['employee_id' => $employee->id], $row)
            );
        }

        return [
            'leave' => $leaves,
            'swap' => $swaps,
            'staff' => $staffRequests,
        ];
    }

    private function scheduleForSwap(
        Employee $employee,
        Shift $shift,
        User $reviewer,
        Carbon $date
    ): ShiftSchedule {
        return ShiftSchedule::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'work_date' => $date->toDateString(),
            ],
            [
                'shift_id' => $shift->id,
                'branch_id' => $employee->branch_id,
                'assignment_type' => 'rotation',
                'status' => 'scheduled',
                'note' => 'Ca mẫu cho luồng đổi ca mobile.',
                'assigned_by' => $reviewer->id,
            ]
        );
    }

    /**
     * @param  array<string, Penalty>  $penalties
     * @param  array<string, Reward>  $rewards
     * @param array{
     *   leave: array<string, LeaveRequest>,
     *   swap: array<string, ShiftSwapRequest>,
     *   staff: array<string, StaffRequest>
     * } $requests
     */
    private function seedNotifications(
        User $user,
        User $reviewer,
        array $penalties,
        array $rewards,
        array $requests
    ): void {
        $rows = [
            [
                'type' => 'penalty_approved',
                'title' => 'Phiếu phạt đã được duyệt',
                'body' => 'Bạn bị trừ 12 điểm do đi trễ.',
                'data' => ['penalty_id' => $penalties['approved']->id],
                'read_at' => null,
            ],
            [
                'type' => 'reward_approved',
                'title' => 'Bạn được cộng điểm thưởng',
                'body' => 'Bạn được cộng 5 điểm vì hỗ trợ ca cao điểm.',
                'data' => ['reward_id' => $rewards['approved']->id],
                'read_at' => null,
            ],
            [
                'type' => 'leave_rejected',
                'title' => 'Đơn nghỉ chưa được duyệt',
                'body' => 'Đơn nghỉ trùng lịch sự kiện tại chi nhánh.',
                'data' => ['leave_request_id' => $requests['leave']['rejected']->id],
                'read_at' => now()->subHours(3),
            ],
            [
                'type' => 'swap_created',
                'title' => 'Yêu cầu đổi ca đang chờ duyệt',
                'body' => 'Quản lý sẽ phản hồi yêu cầu đổi ca của bạn.',
                'data' => ['shift_swap_request_id' => $requests['swap']['pending']->id],
                'read_at' => null,
            ],
            [
                'type' => 'staff_request_approved',
                'title' => 'Yêu cầu công tác đã được duyệt',
                'body' => 'Thời gian ra ngoài đã được ghi nhận.',
                'data' => [
                    'staff_request_id' => $requests['staff']['business_trip']->id,
                ],
                'read_at' => now()->subDay(),
            ],
            [
                'type' => 'shift_checkin_reminder',
                'title' => 'Nhắc check-in ca hôm nay',
                'body' => 'Bạn có ca WFH hôm nay và có thể thử chức năng check-in.',
                'data' => [],
                'read_at' => null,
            ],
        ];

        foreach ($rows as $index => $row) {
            Notification::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => $row['type'],
                    'title' => $row['title'],
                ],
                array_merge($row, [
                    'created_by' => $reviewer->id,
                    'created_at' => now()->subHours($index + 1),
                    'updated_at' => now()->subHours($index + 1),
                ])
            );
        }
    }
}
