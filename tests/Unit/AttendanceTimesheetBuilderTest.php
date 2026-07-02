<?php

namespace Tests\Unit;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Services\AttendanceTimesheetBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTimesheetBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(): Employee
    {
        return Employee::create([
            'code' => 'EMP-' . uniqid(),
            'name' => 'Test Employee',
            'is_active' => true,
        ]);
    }

    private function summaryFor(Employee $employee, $from, $to): array
    {
        $data = (new AttendanceTimesheetBuilder())->build($from, $to, null, null, $employee->id);

        return $data['rows']->firstWhere('employee.id', $employee->id)['summary'];
    }

    public function test_fulltime_shift_gives_flat_one_cong_regardless_of_hours(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-VP', 'name' => 'Văn phòng', 'start_time' => '08:00', 'end_time' => '17:00',
            'shift_type' => 'fulltime', 'standard_work_hours' => 8, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        // Chỉ làm 3 tiếng (thay vì 8) nhưng vẫn chấm đủ vào-ra — ca fulltime vẫn tính đủ 1 công.
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(8, 0), 'check_out_at' => $day->copy()->setTime(11, 0),
        ]);

        $summary = $this->summaryFor($employee, $day, $day);

        $this->assertEquals(1.0, $summary['actual_workdays']);
    }

    public function test_parttime_shift_prorates_cong_by_worked_hours(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-PT', 'name' => 'Part-time cố định', 'start_time' => '08:00', 'end_time' => '18:00',
            'shift_type' => 'parttime', 'standard_work_hours' => 10, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        // Chỉ làm 1 ca 4 tiếng trong khi ca cố định part-time là 10 tiếng/ngày (2 công) → 0.4 công.
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(8, 0), 'check_out_at' => $day->copy()->setTime(12, 0),
        ]);

        $summary = $this->summaryFor($employee, $day, $day);

        $this->assertEquals(0.4, $summary['actual_workdays']);
    }

    public function test_full_credit_log_forces_full_cong_despite_short_hours(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-PT2', 'name' => 'Part-time cố định', 'start_time' => '08:00', 'end_time' => '18:00',
            'shift_type' => 'parttime', 'standard_work_hours' => 10, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        // Đi muộn được duyệt "Công thường" — full_credit=true dù giờ chấm chỉ 4/10.
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(8, 0), 'check_out_at' => $day->copy()->setTime(12, 0),
            'full_credit' => true,
        ]);

        $summary = $this->summaryFor($employee, $day, $day);

        $this->assertEquals(1.0, $summary['actual_workdays']);
    }

    public function test_paid_holiday_with_no_schedule_counts_as_holiday_day(): void
    {
        $employee = $this->makeEmployee();
        $day = now()->startOfDay();
        Holiday::create(['date' => $day->toDateString(), 'name' => 'Test Holiday', 'is_paid' => true, 'bonus_amount' => 200000]);

        $summary = $this->summaryFor($employee, $day, $day);

        $this->assertEquals(1, $summary['holiday_days']);
        $this->assertEquals(200000.0, $summary['holiday_bonus_amount']);
    }

    public function test_working_on_a_holiday_credits_holiday_workdays_not_actual_workdays(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-VP2', 'name' => 'Văn phòng', 'start_time' => '08:00', 'end_time' => '17:00',
            'shift_type' => 'fulltime', 'standard_work_hours' => 8, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        Holiday::create(['date' => $day->toDateString(), 'name' => 'Test Holiday', 'is_paid' => true]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(8, 0), 'check_out_at' => $day->copy()->setTime(17, 0),
        ]);

        $summary = $this->summaryFor($employee, $day, $day);

        $this->assertEquals(1.0, $summary['holiday_workdays']);
        $this->assertEquals(0.0, $summary['actual_workdays']);
    }
}
