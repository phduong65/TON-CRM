<?php

namespace Tests\Unit;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLogComputeCongTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(): Employee
    {
        return Employee::create(['code' => 'EMP-' . uniqid(), 'name' => 'Test Employee', 'is_active' => true]);
    }

    public function test_office_shift_nine_to_eighteen_with_one_hour_break_is_one_cong(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-VP', 'name' => 'Văn phòng', 'start_time' => '09:00', 'end_time' => '18:00',
            'break_minutes' => 60, 'shift_type' => 'fulltime', 'standard_work_hours' => 8, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(9, 0), 'check_out_at' => $day->copy()->setTime(18, 0),
        ]);

        $this->assertEquals(8.0, $log->netWorkedHours());
        $this->assertEquals(1.0, $log->computeCong());
    }

    public function test_restaurant_shift_eleven_to_fifteen_is_zero_point_four_cong_of_ten_hour_day(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-NH', 'name' => 'Nhà hàng', 'start_time' => '11:00', 'end_time' => '15:00',
            'break_minutes' => 0, 'shift_type' => 'parttime', 'standard_work_hours' => 10, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(11, 0), 'check_out_at' => $day->copy()->setTime(15, 0),
        ]);

        $this->assertEquals(4.0, $log->netWorkedHours());
        $this->assertEquals(0.4, $log->computeCong());
    }

    public function test_full_credit_forces_one_cong_regardless_of_shift_type(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-NH2', 'name' => 'Nhà hàng', 'start_time' => '11:00', 'end_time' => '15:00',
            'shift_type' => 'parttime', 'standard_work_hours' => 10, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(11, 0), 'check_out_at' => $day->copy()->setTime(15, 0),
            'full_credit' => true,
        ]);

        $this->assertEquals(1.0, $log->computeCong());
    }

    public function test_missing_check_in_or_out_returns_null(): void
    {
        $employee = $this->makeEmployee();
        $day = now()->startOfDay();
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(9, 0),
        ]);

        $this->assertNull($log->netWorkedHours());
        $this->assertNull($log->computeCong());
    }

    public function test_off_schedule_log_falls_back_to_default_eight_hour_ratio(): void
    {
        $employee = $this->makeEmployee();
        $day = now()->startOfDay();
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(8, 0), 'check_out_at' => $day->copy()->setTime(12, 0),
        ]);

        $this->assertEquals(4.0, $log->netWorkedHours());
        $this->assertEquals(0.5, $log->computeCong());
    }

    public function test_early_check_in_is_clamped_to_shift_start(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-NH3', 'name' => 'Nhà hàng', 'start_time' => '11:00', 'end_time' => '15:00',
            'break_minutes' => 0, 'shift_type' => 'parttime', 'standard_work_hours' => 10, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        // Chấm công vào lúc 10:00 (sớm 1 tiếng so với giờ vào ca 11:00) — giờ sớm này không được tính.
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(10, 0), 'check_out_at' => $day->copy()->setTime(15, 0),
        ]);

        $this->assertEquals(4.0, $log->netWorkedHours());
        $this->assertEquals(0.4, $log->computeCong());
    }

    public function test_late_check_out_is_clamped_to_shift_end(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-NH4', 'name' => 'Nhà hàng', 'start_time' => '11:00', 'end_time' => '15:00',
            'break_minutes' => 0, 'shift_type' => 'parttime', 'standard_work_hours' => 10, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        // Check-out lúc 16:30 (muộn 1.5 tiếng so với giờ ra ca 15:00) — giờ muộn này không được tính.
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(11, 0), 'check_out_at' => $day->copy()->setTime(16, 30),
        ]);

        $this->assertEquals(4.0, $log->netWorkedHours());
        $this->assertEquals(0.4, $log->computeCong());
    }

    public function test_early_check_in_and_late_check_out_both_clamped_for_office_shift(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-VP2', 'name' => 'Văn phòng', 'start_time' => '09:00', 'end_time' => '18:00',
            'break_minutes' => 60, 'shift_type' => 'fulltime', 'standard_work_hours' => 8, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        // Vào sớm 8:00 (sớm 1h), ra muộn 19:00 (muộn 1h) — công vẫn tính đúng 8 giờ công / 1 công.
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(8, 0), 'check_out_at' => $day->copy()->setTime(19, 0),
        ]);

        $this->assertEquals(8.0, $log->netWorkedHours());
        $this->assertEquals(1.0, $log->computeCong());
    }

    public function test_flexible_shift_twelve_to_twenty_is_one_cong(): void
    {
        $employee = $this->makeEmployee();
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => null,
            'custom_start_time' => '12:00', 'custom_end_time' => '20:00', 'custom_break_minutes' => 0,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(12, 0), 'check_out_at' => $day->copy()->setTime(20, 0),
        ]);

        $this->assertEquals(8.0, $log->netWorkedHours());
        $this->assertEquals(1.0, $log->computeCong());
    }

    public function test_late_check_in_and_early_check_out_are_not_clamped(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-NH5', 'name' => 'Nhà hàng', 'start_time' => '11:00', 'end_time' => '15:00',
            'break_minutes' => 0, 'shift_type' => 'parttime', 'standard_work_hours' => 10, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        // Vào muộn (11:30) và ra sớm (14:30) — đây là thiếu giờ thật sự, không phải "sớm/muộn có lợi", nên vẫn tính đúng giờ thực tế (3h).
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(11, 30), 'check_out_at' => $day->copy()->setTime(14, 30),
        ]);

        $this->assertEquals(3.0, $log->netWorkedHours());
        $this->assertEquals(0.3, $log->computeCong());
    }
}
