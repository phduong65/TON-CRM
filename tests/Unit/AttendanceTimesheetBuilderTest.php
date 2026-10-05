<?php

namespace Tests\Unit;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
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

    public function test_fulltime_shift_prorates_cong_by_worked_hours(): void
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
        // Chỉ làm 3 tiếng (thay vì 8) — kể cả ca fulltime, công luôn quy đổi theo giờ thực tế:
        // 3h/8h chuẩn = 0.38, KHÔNG còn tính đủ 1 công như trước.
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(8, 0), 'check_out_at' => $day->copy()->setTime(11, 0),
        ]);

        $summary = $this->summaryFor($employee, $day, $day);

        $this->assertEquals(0.38, $summary['actual_workdays']);
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

    public function test_full_credit_log_still_prorates_cong_by_worked_hours(): void
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
        // Đi muộn được duyệt "Công thường" (full_credit=true) — chỉ tha lỗi kỷ luật, KHÔNG còn
        // nâng công: vẫn tính đúng theo giờ chấm công thực tế 4h/10h = 0.4.
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(8, 0), 'check_out_at' => $day->copy()->setTime(12, 0),
            'full_credit' => true,
        ]);

        $summary = $this->summaryFor($employee, $day, $day);

        $this->assertEquals(0.4, $summary['actual_workdays']);
    }

    public function test_overtime_hours_add_extra_cong_on_top_of_normal_workday(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-VP3', 'name' => 'Văn phòng', 'start_time' => '08:00', 'end_time' => '17:00',
            'shift_type' => 'fulltime', 'standard_work_hours' => 8, 'work_mode' => 'onsite',
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(8, 0), 'check_out_at' => $day->copy()->setTime(17, 0),
            'overtime_hours' => 4,
        ]);

        $summary = $this->summaryFor($employee, $day, $day);

        // Ca fulltime 08:00-17:00 không có break_minutes = 9h làm việc / 8h chuẩn = 1.125 công
        // + 4h tăng ca / 8h chuẩn = 0.5 công => 1.63 công (công luôn tính theo giờ thực tế).
        $this->assertEquals(1.63, $summary['actual_workdays']);
        $this->assertEquals(1, $summary['overtime_shifts']);
        $this->assertEquals(4.0, $summary['overtime_hours']);
        $this->assertEquals(4.0, $summary['extra_hours']);
        // Giờ làm thực tế (check-in → check-out) = 9h, không tính riêng giờ tăng ca.
        $this->assertEquals(9.0, $summary['worked_hours']);
    }

    public function test_worked_hours_sums_actual_check_in_out_hours_across_the_period(): void
    {
        $employee = $this->makeEmployee();
        $shift = Shift::create([
            'code' => 'CA-VP4', 'name' => 'Văn phòng', 'start_time' => '08:00', 'end_time' => '17:00',
            'shift_type' => 'fulltime', 'standard_work_hours' => 8, 'work_mode' => 'onsite',
        ]);
        $day1 = now()->startOfDay();
        $day2 = $day1->copy()->addDay();

        $schedule1 = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day1->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule1->id, 'work_date' => $day1->toDateString(),
            'check_in_at' => $day1->copy()->setTime(8, 0), 'check_out_at' => $day1->copy()->setTime(17, 0),
        ]);

        // Chấm công ngày không có lịch xếp ca (đi làm ngoài lịch) — vẫn cộng dồn giờ làm.
        AttendanceLog::create([
            'employee_id' => $employee->id, 'work_date' => $day2->toDateString(),
            'check_in_at' => $day2->copy()->setTime(9, 0), 'check_out_at' => $day2->copy()->setTime(13, 0),
        ]);

        $summary = $this->summaryFor($employee, $day1, $day2);

        $this->assertEquals(13.0, $summary['worked_hours']);
    }

    public function test_overtime_only_log_on_day_off_still_counts_as_overtime_shift(): void
    {
        $employee = $this->makeEmployee();
        $day = now()->startOfDay();
        AttendanceLog::create([
            'employee_id' => $employee->id, 'work_date' => $day->toDateString(),
            'overtime_hours' => 3,
        ]);

        $summary = $this->summaryFor($employee, $day, $day);

        // Không có ca -> mặc định 8h chuẩn: 3h tăng ca / 8h = 0.38 công (làm tròn 2 số lẻ).
        $this->assertEquals(0.38, $summary['actual_workdays']);
        $this->assertEquals(1, $summary['overtime_shifts']);
        $this->assertEquals(3.0, $summary['overtime_hours']);
    }

    public function test_paid_holiday_with_no_schedule_counts_as_holiday_day(): void
    {
        $employee = $this->makeEmployee();
        // Ngày cố định (Thứ 4) thay vì now() — tránh test "ăn may" theo ngày chạy suite
        // (nếu now() rơi đúng Chủ nhật, ngày nghỉ hàng tuần sẵn có của NV không văn phòng,
        // logic mới ở dưới sẽ không cộng "Nghỉ lễ" và làm assertion sai một cách ngẫu nhiên).
        $day = \Carbon\Carbon::parse('2026-08-19');
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
        $day = \Carbon\Carbon::parse('2026-08-19');
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

        // Ca 08:00-17:00 không có break_minutes = 9h làm việc / 8h chuẩn = 1.13 công — vẫn được
        // cộng vào holiday_workdays (không phải actual_workdays) vì rơi đúng ngày lễ.
        $this->assertEquals(1.13, $summary['holiday_workdays']);
        $this->assertEquals(0.0, $summary['actual_workdays']);
    }

    /**
     * "Nghỉ ốm" (sick) và "Khác" (other) đã bị bỏ khỏi lựa chọn khi tạo đơn mới, nhưng dữ liệu
     * cũ với 2 type này vẫn còn trong DB — "Bảng chấm công" chỉ phân biệt 2 nhóm có lương/không
     * lương: CHỈ "annual" tính có lương (NC), mọi type khác (kể cả sick/other cũ) tính không
     * lương (NK). Xem AttendanceTimesheetBuilder::buildEmployeeRow().
     */
    public function test_only_annual_type_counts_as_paid_leave_others_count_as_unpaid(): void
    {
        $employee = $this->makeEmployee();
        $day1 = now()->startOfDay();
        $day2 = $day1->copy()->addDay();
        $day3 = $day1->copy()->addDays(2);

        LeaveRequest::create([
            'code' => 'LR-1', 'employee_id' => $employee->id,
            'date_from' => $day1->toDateString(), 'date_to' => $day1->toDateString(),
            'type' => 'annual', 'reason' => 'x', 'status' => 'approved',
        ]);
        LeaveRequest::create([
            'code' => 'LR-2', 'employee_id' => $employee->id,
            'date_from' => $day2->toDateString(), 'date_to' => $day2->toDateString(),
            'type' => 'sick', 'reason' => 'x', 'status' => 'approved',
        ]);
        LeaveRequest::create([
            'code' => 'LR-3', 'employee_id' => $employee->id,
            'date_from' => $day3->toDateString(), 'date_to' => $day3->toDateString(),
            'type' => 'other', 'reason' => 'x', 'status' => 'approved',
        ]);

        $summary = $this->summaryFor($employee, $day1, $day3);

        $this->assertEquals(1.0, $summary['paid_leave_days']);
        $this->assertEquals(2.0, $summary['unpaid_leave_days']);
    }

    /**
     * "Công chuẩn" phải tính RIÊNG theo từng nhân viên: NV văn phòng (is_office=true) nghỉ cả
     * Thứ 7 + Chủ nhật (tuần làm 5 ngày), các NV còn lại chỉ nghỉ Chủ nhật (tuần làm 6 ngày).
     * Trước đây dùng chung 1 con số cho mọi nhân viên — sai cho khối văn phòng.
     */
    public function test_standard_workdays_differ_between_office_and_non_office_employees(): void
    {
        $officeEmployee = $this->makeEmployee();
        $officeEmployee->update(['is_office' => true]);
        $nonOfficeEmployee = $this->makeEmployee();

        $from = now()->startOfWeek(); // Thứ hai
        $to   = $from->copy()->addDays(6); // Chủ nhật cùng tuần — đủ 7 ngày

        $officeSummary    = $this->summaryFor($officeEmployee, $from, $to);
        $nonOfficeSummary = $this->summaryFor($nonOfficeEmployee, $from, $to);

        $this->assertEquals(5, $officeSummary['standard_workdays']);
        $this->assertEquals(6, $nonOfficeSummary['standard_workdays']);
    }

    /**
     * Ngày lễ trùng đúng ngày nghỉ hàng tuần sẵn có của nhân viên (VD lễ rơi vào Chủ nhật cho
     * NV thường, hoặc Thứ 7 cho NV văn phòng) — không được cộng thêm "Nghỉ lễ", vì nhân viên
     * vốn dĩ không phải đi làm ngày đó, cộng thêm sẽ bị trùng công. Theo quyết định của người
     * dùng: không cộng thêm nếu trùng ngày nghỉ sẵn có, không cần theo dõi "nghỉ bù" riêng.
     */
    public function test_holiday_on_existing_weekly_rest_day_does_not_double_count(): void
    {
        $nonOfficeEmployee = $this->makeEmployee();
        $officeEmployee = $this->makeEmployee();
        $officeEmployee->update(['is_office' => true]);

        $sunday = \Carbon\Carbon::parse('2026-08-23'); // Chủ nhật — nghỉ hàng tuần của cả 2 nhóm
        $saturday = \Carbon\Carbon::parse('2026-08-22'); // Thứ 7 — chỉ là ngày nghỉ hàng tuần của NV văn phòng

        Holiday::create(['date' => $sunday->toDateString(), 'name' => 'Lễ Chủ nhật', 'is_paid' => true, 'bonus_amount' => 200000]);
        Holiday::create(['date' => $saturday->toDateString(), 'name' => 'Lễ Thứ 7', 'is_paid' => true, 'bonus_amount' => 100000]);

        // Chủ nhật: cả 2 nhóm đều đã nghỉ hàng tuần sẵn — không nhóm nào được cộng "Nghỉ lễ".
        $nonOfficeSunday = $this->summaryFor($nonOfficeEmployee, $sunday, $sunday);
        $officeSunday = $this->summaryFor($officeEmployee, $sunday, $sunday);
        $this->assertEquals(0, $nonOfficeSunday['holiday_days']);
        $this->assertEquals(0.0, $nonOfficeSunday['holiday_bonus_amount']);
        $this->assertEquals(0, $officeSunday['holiday_days']);
        $this->assertEquals(0.0, $officeSunday['holiday_bonus_amount']);

        // Thứ 7: NV văn phòng đã nghỉ hàng tuần sẵn (không cộng), NV thường vẫn đi làm bình
        // thường ngày Thứ 7 nên vẫn được cộng "Nghỉ lễ" bình thường.
        $nonOfficeSaturday = $this->summaryFor($nonOfficeEmployee, $saturday, $saturday);
        $officeSaturday = $this->summaryFor($officeEmployee, $saturday, $saturday);
        $this->assertEquals(1, $nonOfficeSaturday['holiday_days']);
        $this->assertEquals(100000.0, $nonOfficeSaturday['holiday_bonus_amount']);
        $this->assertEquals(0, $officeSaturday['holiday_days']);
        $this->assertEquals(0.0, $officeSaturday['holiday_bonus_amount']);
    }

    /**
     * Nhân viên trong bảng phải sắp theo Chi nhánh → Tên (không còn theo Chức danh) — để các
     * nhân viên cùng chi nhánh đứng gần nhau, dễ nhìn/quản lý khi xuất Excel. Không hiển thị
     * tag/dòng tiêu đề chi nhánh, chỉ ảnh hưởng thứ tự hàng.
     */
    public function test_employees_are_ordered_by_branch_then_name(): void
    {
        $branchB = \App\Models\Branch::create(['code' => 'BR-B', 'name' => 'Chi nhánh B', 'is_active' => true]);
        $branchA = \App\Models\Branch::create(['code' => 'BR-A', 'name' => 'Chi nhánh A', 'is_active' => true]);

        $zed = Employee::create(['code' => 'EMP-Z', 'name' => 'Zed', 'branch_id' => $branchA->id, 'is_active' => true]);
        $anna = Employee::create(['code' => 'EMP-A', 'name' => 'Anna', 'branch_id' => $branchA->id, 'is_active' => true]);
        $bob = Employee::create(['code' => 'EMP-B', 'name' => 'Bob', 'branch_id' => $branchB->id, 'is_active' => true]);

        $day = now()->startOfDay();
        $data = (new AttendanceTimesheetBuilder())->build($day, $day, null, null, null);

        $orderedNames = $data['rows']->pluck('employee.name')->all();

        $this->assertSame(['Anna', 'Zed', 'Bob'], $orderedNames);
    }
}
