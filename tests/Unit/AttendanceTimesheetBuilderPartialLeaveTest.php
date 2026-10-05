<?php

namespace Tests\Unit;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Services\AttendanceTimesheetBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Xác nhận "Bảng chấm công" chia đúng công/ngày phép cho ngày có nghỉ theo giờ: KHÔNG bỏ qua
 * hẳn phần chấm công thực tế (như nghỉ cả ngày) và KHÔNG tính đủ 1 công như bình thường —
 * công = 1 - day_fraction, ngày phép = day_fraction (xem AttendanceTimesheetBuilder::buildEmployeeRow()).
 */
class AttendanceTimesheetBuilderPartialLeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_day_leave_splits_workday_and_leave_credit(): void
    {
        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        $employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'branch_id' => $branch->id,
            'is_active' => true, 'shift_type' => 'fulltime',
        ]);

        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '09:00', 'end_time' => '18:00',
            'break_minutes' => 60, 'work_mode' => 'onsite', 'shift_type' => 'fulltime',
        ]);

        $workDate = Carbon::today();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id, 'branch_id' => $branch->id,
            'work_date' => $workDate->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
            'adjusted_start_time' => '12:00',
        ]);

        $leave = LeaveRequest::create([
            'code' => 'LR-TS-01', 'employee_id' => $employee->id,
            'date_from' => $workDate->toDateString(), 'date_to' => $workDate->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ sáng', 'status' => 'approved',
            'is_partial_day' => true, 'from_time' => '09:00', 'to_time' => '12:00',
            'day_fraction' => 0.33, 'shift_schedule_id' => $schedule->id,
        ]);

        // Nhân viên đã hoàn thành chấm công phần còn lại (12h-18h).
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id,
            'work_date' => $workDate->toDateString(),
            'check_in_at' => $workDate->copy()->setTime(12, 0),
            'check_out_at' => $workDate->copy()->setTime(18, 0),
            'check_in_method' => 'gps_ip', 'check_out_method' => 'gps_ip',
            'shift_start_time' => '12:00', 'shift_end_time' => '18:00', 'shift_break_minutes' => 0,
            'shift_type' => 'fulltime', 'shift_standard_work_hours' => 8,
            'late_minutes' => 0, 'early_minutes' => 0,
        ]);

        $result = (new AttendanceTimesheetBuilder())->build($workDate, $workDate, null, null, $employee->id);
        $summary = $result['rows']->first()['summary'];

        $this->assertEquals(0.67, $summary['actual_workdays']);
        $this->assertEquals(0.33, $summary['paid_leave_days']);
        $this->assertEquals(1.0, $summary['payroll_workdays']);
    }

    public function test_partial_day_leave_gives_zero_workday_credit_when_worked_portion_not_attended(): void
    {
        $branch = Branch::create(['code' => 'BR-2', 'name' => 'Chi nhánh 2', 'is_active' => true]);
        $employee = Employee::create([
            'code' => 'EMP-02', 'name' => 'Trần Thị B', 'branch_id' => $branch->id, 'is_active' => true,
        ]);

        $shift = Shift::create([
            'code' => 'CA-HC2', 'name' => 'Ca hành chính', 'start_time' => '09:00', 'end_time' => '18:00',
            'break_minutes' => 60, 'work_mode' => 'onsite',
        ]);

        $workDate = Carbon::today();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id, 'branch_id' => $branch->id,
            'work_date' => $workDate->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
            'adjusted_start_time' => '12:00',
        ]);

        LeaveRequest::create([
            'code' => 'LR-TS-02', 'employee_id' => $employee->id,
            'date_from' => $workDate->toDateString(), 'date_to' => $workDate->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ sáng', 'status' => 'approved',
            'is_partial_day' => true, 'from_time' => '09:00', 'to_time' => '12:00',
            'day_fraction' => 0.33, 'shift_schedule_id' => $schedule->id,
        ]);

        // Không chấm công phần còn lại (bỏ luôn buổi chiều).
        $result = (new AttendanceTimesheetBuilder())->build($workDate, $workDate, null, null, $employee->id);
        $summary = $result['rows']->first()['summary'];

        $this->assertEquals(0.0, $summary['actual_workdays']);
        $this->assertEquals(0.33, $summary['paid_leave_days']);
        $this->assertEquals(0.33, $summary['payroll_workdays']);
    }

    public function test_partial_day_leave_on_one_shift_does_not_drop_other_shift_same_day(): void
    {
        $branch = Branch::create(['code' => 'BR-3', 'name' => 'Chi nhánh 3', 'is_active' => true]);
        $employee = Employee::create([
            'code' => 'EMP-03', 'name' => 'Lê Văn C', 'branch_id' => $branch->id, 'is_active' => true,
        ]);

        $morningShift = Shift::create([
            'code' => 'CA-SANG', 'name' => 'Ca sáng', 'start_time' => '08:00', 'end_time' => '12:00',
            'break_minutes' => 0, 'work_mode' => 'onsite',
        ]);
        $eveningShift = Shift::create([
            'code' => 'CA-TOI', 'name' => 'Ca tối', 'start_time' => '18:00', 'end_time' => '22:00',
            'break_minutes' => 0, 'work_mode' => 'onsite',
        ]);

        $workDate = Carbon::today();

        $morningSchedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $morningShift->id, 'branch_id' => $branch->id,
            'work_date' => $workDate->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
            'adjusted_start_time' => '10:00',
        ]);
        $eveningSchedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $eveningShift->id, 'branch_id' => $branch->id,
            'work_date' => $workDate->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        // Nghỉ theo giờ chỉ gắn với ca sáng (08h-10h).
        LeaveRequest::create([
            'code' => 'LR-TS-03', 'employee_id' => $employee->id,
            'date_from' => $workDate->toDateString(), 'date_to' => $workDate->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ đầu ca sáng', 'status' => 'approved',
            'is_partial_day' => true, 'from_time' => '08:00', 'to_time' => '10:00',
            'day_fraction' => 0.25, 'shift_schedule_id' => $morningSchedule->id,
        ]);

        // Ca sáng: đã chấm công phần còn lại (10h-12h).
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $morningSchedule->id,
            'work_date' => $workDate->toDateString(),
            'check_in_at' => $workDate->copy()->setTime(10, 0),
            'check_out_at' => $workDate->copy()->setTime(12, 0),
            'check_in_method' => 'gps_ip', 'check_out_method' => 'gps_ip',
            'shift_start_time' => '10:00', 'shift_end_time' => '12:00', 'shift_break_minutes' => 0,
            'shift_type' => 'parttime', 'shift_standard_work_hours' => 2,
            'late_minutes' => 0, 'early_minutes' => 0,
        ]);

        // Ca tối: chấm công đầy đủ, không liên quan gì tới đơn nghỉ.
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $eveningSchedule->id,
            'work_date' => $workDate->toDateString(),
            'check_in_at' => $workDate->copy()->setTime(18, 0),
            'check_out_at' => $workDate->copy()->setTime(22, 0),
            'check_in_method' => 'gps_ip', 'check_out_method' => 'gps_ip',
            'shift_start_time' => '18:00', 'shift_end_time' => '22:00', 'shift_break_minutes' => 0,
            'shift_type' => 'parttime', 'shift_standard_work_hours' => 4,
            'late_minutes' => 0, 'early_minutes' => 0,
        ]);

        $result = (new AttendanceTimesheetBuilder())->build($workDate, $workDate, null, null, $employee->id);
        $summary = $result['rows']->first()['summary'];

        // Công ca tối KHÔNG được bị bỏ sót chỉ vì cùng ngày có nghỉ theo giờ gắn với ca sáng.
        $this->assertGreaterThan(0.0, $summary['actual_workdays']);
        $this->assertEquals(0.25, $summary['paid_leave_days']);
    }
}
