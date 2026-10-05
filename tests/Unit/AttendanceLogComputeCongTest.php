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

    public function test_full_credit_no_longer_boosts_cong_always_uses_actual_hours(): void
    {
        // full_credit (đơn "Đi muộn về sớm" duyệt "Công thường") chỉ còn tha lỗi kỷ luật
        // (late_minutes/early_minutes = 0), KHÔNG còn nâng công lên mức làm đủ ca — công luôn
        // tính theo giờ chấm công thực tế (2h/10h = 0.2) bất kể full_credit true hay false.
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
            'check_in_at' => $day->copy()->setTime(11, 0), 'check_out_at' => $day->copy()->setTime(13, 0),
            'full_credit' => true,
        ]);

        $this->assertEquals(0.2, $log->computeCong());

        $log->update(['full_credit' => false]);
        $this->assertEquals(0.2, $log->fresh()->computeCong());
    }

    public function test_full_credit_on_overnight_shift_credits_shift_duration_even_if_checked_out_late(): void
    {
        // Ca qua đêm 17:45-00:00 (6.25h theo lịch, chuẩn ngày 10h), nhân viên ở lại tới 01:15.
        // netWorkedHours() cắt theo khung ca nên phần sau 00:00 không tính thêm (muốn tính phải
        // qua yêu cầu "Tăng ca") — công đúng bằng trọn ca: 6.25h/10h = 0.63.
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-TOI-OV', 'name' => 'Ca tối', 'start_time' => '17:45', 'end_time' => '00:00',
            'shift_type' => 'parttime', 'standard_work_hours' => 10, 'is_overnight' => true, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(17, 45), 'check_out_at' => $day->copy()->addDay()->setTime(1, 15),
            'full_credit' => true,
        ]);

        $this->assertEquals(0.63, $log->computeCong());
    }

    public function test_full_credit_without_resolvable_shift_uses_default_eight_hour_ratio(): void
    {
        // Không xác định được ca (chấm công ngoài lịch, không có snapshot giờ ca) — full_credit
        // không còn boost công, quy đổi bình thường theo giờ chuẩn mặc định 8h: 3h/8h = 0.38.
        $employee = $this->makeEmployee();
        $day = now()->startOfDay();
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(9, 0), 'check_out_at' => $day->copy()->setTime(12, 0),
            'full_credit' => true,
        ]);

        $this->assertEquals(0.38, $log->computeCong());
    }

    public function test_full_credit_on_single_shift_day_uses_actual_hours(): void
    {
        // Ca đơn 8h chuẩn nhưng chỉ làm 6h rồi về sớm — dù có full_credit (đã tha lỗi kỷ luật),
        // công vẫn tính đúng theo giờ thực tế: 6h/8h = 0.75, không còn được nâng lên 1.0.
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-PT8', 'name' => 'Ca 8h', 'start_time' => '09:00', 'end_time' => '17:00',
            'shift_type' => 'parttime', 'standard_work_hours' => 8, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(9, 0), 'check_out_at' => $day->copy()->setTime(15, 0),
            'full_credit' => true,
        ]);

        $this->assertEquals(0.75, $log->computeCong());
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

    public function test_snapshot_keeps_correct_cong_after_shift_schedule_is_deleted(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-NH6', 'name' => 'Nhà hàng', 'start_time' => '09:00', 'end_time' => '15:00',
            'break_minutes' => 0, 'shift_type' => 'parttime', 'standard_work_hours' => 6, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(8, 30), 'check_out_at' => $day->copy()->setTime(16, 0),
            ...$schedule->shiftSnapshotAttributes(),
        ]);

        // Ca xếp bị xoá sau đó (VD xếp lại lịch tuần) — shift_schedule_id bị nullOnDelete().
        $schedule->delete();
        $log->refresh();

        $this->assertNull($log->shift_schedule_id);
        $this->assertEquals(6.0, $log->netWorkedHours());
        $this->assertEquals(1.0, $log->computeCong());
    }

    public function test_overnight_shift_still_computes_correct_hours_when_is_overnight_flag_is_wrong(): void
    {
        // Tái hiện lỗi thực tế: ca "Ca Bếp tối" 18h-24h nhưng cờ is_overnight bị để sai (false) —
        // trước đây khiến scheduledEnd nằm TRƯỚC scheduledStart, checkOut sau khi clamp <= checkIn,
        // netWorkedHours() trả về 0 dù nhân viên chấm công đủ (check-in 17:57, check-out 00:10 hôm sau).
        // Giờ suy ra qua đêm trực tiếp từ end_time <= start_time nên phải ra đúng 6 giờ công.
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-BEP-TOI', 'name' => 'Ca Bếp tối', 'start_time' => '18:00', 'end_time' => '00:00',
            'is_overnight' => false, 'break_minutes' => 0, 'shift_type' => 'parttime',
            'standard_work_hours' => 6, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(17, 57), 'check_out_at' => $day->copy()->addDay()->setTime(0, 10),
        ]);

        $this->assertEquals(6.0, $log->netWorkedHours());
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
