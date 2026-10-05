<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Team;
use App\Services\AttendanceTimesheetBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Bảng chấm công" (export Excel) trước đây có cột Chi nhánh/Phòng ban và cặp cột "Chính
 * thức/Thử việc" (cột Thử việc luôn = 0 vì hệ thống không lưu trạng thái thử việc — dead
 * weight). Đã bỏ 2 loại cột trên, thêm phần ghi chú viết tắt (NC/NK/NL) — verify view render
 * đúng thay đổi. Xem resources/views/exports/attendance-timesheet.blade.php.
 *
 * Chi nhánh/Phòng ban không hiển thị lại dưới bất kỳ hình thức nào (không cột, không dòng tiêu
 * đề nhóm) — chỉ ẢNH HƯỞNG thứ tự sắp xếp nhân viên (theo Chi nhánh → Tên, xem
 * AttendanceTimesheetBuilder::build()), verify riêng ở AttendanceTimesheetBuilderTest.
 */
class AttendanceTimesheetExportViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_view_removes_branch_team_and_probation_columns_and_shows_legend(): void
    {
        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh Test', 'is_active' => true]);
        $team = Team::create(['code' => 'TEAM-1', 'name' => 'Phòng Test', 'branch_id' => $branch->id, 'is_active' => true]);
        $position = Position::create(['name' => 'Nhân viên', 'is_active' => true]);
        $employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'branch_id' => $branch->id, 'team_id' => $team->id,
            'position_id' => $position->id, 'is_active' => true,
        ]);

        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00',
            'work_mode' => 'onsite', 'standard_work_hours' => 8,
        ]);
        $day = now()->startOfDay();
        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id,
            'work_date' => $day->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $day->toDateString(),
            'check_in_at' => $day->copy()->setTime(8, 0), 'check_out_at' => $day->copy()->setTime(17, 0),
        ]);

        $data = (new AttendanceTimesheetBuilder())->build($day, $day, null, null, null);

        $html = view('exports.attendance-timesheet', [
            'days'       => $data['days'],
            'rows'       => $data['rows'],
            'rangeLabel' => 'Test range',
        ])->render();

        $this->assertStringNotContainsString('Chi nhánh', $html);
        $this->assertStringNotContainsString('Phòng ban', $html);
        $this->assertStringNotContainsString('Thử việc', $html);
        $this->assertStringNotContainsString('Chính thức', $html);

        // "Tổng giờ làm thêm giờ" (extra_hours) trùng 100% giá trị với "Tổng giờ tăng ca"
        // (overtime_hours, cùng lấy từ AttendanceLog::overtime_hours) — chỉ giữ 1 cột.
        $this->assertStringNotContainsString('Tổng giờ làm thêm giờ', $html);
        $this->assertStringContainsString('Tổng giờ tăng ca', $html);

        // Đi muộn/về sớm đã có ở trang "Vi phạm" riêng — bỏ khỏi bảng công.
        $this->assertStringNotContainsString('Số lần đi muộn', $html);
        $this->assertStringNotContainsString('Số lần về sớm', $html);

        $this->assertStringContainsString($employee->name, $html);
        $this->assertStringContainsString($employee->position->name, $html);

        // Ghi chú viết tắt.
        $this->assertStringContainsString('Ghi chú các ký hiệu viết tắt', $html);
        $this->assertStringContainsString('Nghỉ có lương', $html);
        $this->assertStringContainsString('Nghỉ không lương', $html);
        $this->assertStringContainsString('Nghỉ lễ', $html);
    }
}
