<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MyScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'staff']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-own-schedule']));

        $this->user = User::factory()->create();
        $this->user->assignRole('staff');

        $this->employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'user_id' => $this->user->id, 'is_active' => true,
        ]);
    }

    // ── Trang khung ──────────────────────────────────────────────────────

    public function test_index_page_renders_calendar_container(): void
    {
        $response = $this->actingAs($this->user)->get(route('my-schedule.index'));

        $response->assertStatus(200);
        $response->assertSee('id="workCalendar"', false);
        $response->assertSee('FullCalendar', false);
    }

    /**
     * Ngày ĐA CA (VD Bếp sáng + Bếp tối cùng ngày): mỗi ca có lượt chấm công RIÊNG, trang lịch phải
     * hiển thị đúng giờ vào/ra của TỪNG ca — không dùng chung 1 log/ngày (bug keyBy theo ngày cũ
     * khiến 2 ca hiện trùng 1 giờ). Tái hiện đúng ảnh chụp lỗi người dùng gửi.
     */
    public function test_multi_shift_day_shows_each_shift_its_own_check_in_out(): void
    {
        $workDate = now()->toDateString();

        $morning = Shift::create(['code' => 'CA-BS', 'name' => 'Ca Bếp sáng', 'start_time' => '11:00', 'end_time' => '15:00', 'work_mode' => 'onsite']);
        $evening = Shift::create(['code' => 'CA-BT', 'name' => 'Ca Bếp tối', 'start_time' => '18:00', 'end_time' => '23:00', 'work_mode' => 'onsite']);

        $schedMorning = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $morning->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $schedEvening = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $evening->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'shift_schedule_id' => $schedMorning->id, 'work_date' => $workDate,
            'check_in_at' => $workDate . ' 11:03:00', 'check_out_at' => $workDate . ' 15:02:00',
        ]);
        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'shift_schedule_id' => $schedEvening->id, 'work_date' => $workDate,
            'check_in_at' => $workDate . ' 18:05:00', 'check_out_at' => $workDate . ' 23:10:00',
        ]);

        // Bản render mobile (danh sách thẻ ca) mới hiển thị giờ vào/ra — cần User-Agent mobile để
        // DetectMobileDevice bật $isMobileDevice (bản desktop dùng FullCalendar + feed events()).
        $response = $this->actingAs($this->user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'])
            ->get(route('my-schedule.index'));

        $response->assertStatus(200);
        // Mỗi ca hiện đúng giờ của mình — cả 4 mốc đều phải xuất hiện.
        $response->assertSee('11:03')->assertSee('15:02')->assertSee('18:05')->assertSee('23:10');
    }

    /**
     * Ca linh hoạt (shift_id null, giờ lấy từ custom_start_time/custom_end_time): trang lịch từng
     * crash 500 "Attempt to read property start_time on null" do view đọc thẳng $schedule->shift.
     */
    public function test_index_renders_flexible_shift_without_shift_template(): void
    {
        $workDate = now()->toDateString();
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => null,
            'custom_start_time' => '10:00', 'custom_end_time' => '14:30',
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'])
            ->get(route('my-schedule.index'));

        $response->assertStatus(200);
        $response->assertSee('Ca linh hoạt')->assertSee('10:00 – 14:30');

        // Feed FullCalendar cũng phải hiện ca linh hoạt (trước đây bị bỏ qua).
        $events = $this->actingAs($this->user)->getJson(route('my-schedule.events', $this->eventsRange()));
        $events->assertStatus(200);
        $this->assertStringContainsString('Ca linh hoạt (10:00–14:30)', $events->getContent());
    }

    /**
     * Cùng bug ở JSON feed FullCalendar: mỗi event ca lấy đúng lượt chấm công của ca đó
     * (checkInAt), không dùng chung log theo ngày.
     */
    public function test_events_feed_matches_attendance_per_shift_on_multi_shift_day(): void
    {
        $workDate = now()->toDateString();

        $morning = Shift::create(['code' => 'CA-BS2', 'name' => 'Ca Bếp sáng', 'start_time' => '11:00', 'end_time' => '15:00', 'work_mode' => 'onsite']);
        $evening = Shift::create(['code' => 'CA-BT2', 'name' => 'Ca Bếp tối', 'start_time' => '18:00', 'end_time' => '23:00', 'work_mode' => 'onsite']);

        $schedMorning = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $morning->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $schedEvening = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $evening->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'shift_schedule_id' => $schedMorning->id, 'work_date' => $workDate,
            'check_in_at' => $workDate . ' 11:03:00', 'check_out_at' => $workDate . ' 15:02:00',
        ]);
        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'shift_schedule_id' => $schedEvening->id, 'work_date' => $workDate,
            'check_in_at' => $workDate . ' 18:05:00', 'check_out_at' => $workDate . ' 23:10:00',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.events', $this->eventsRange()));

        $response->assertStatus(200);
        $response->assertJsonFragment(['checkInAt' => '11:03', 'checkOutAt' => '15:02']);
        $response->assertJsonFragment(['checkInAt' => '18:05', 'checkOutAt' => '23:10']);
    }

    public function test_attendance_summary_applies_partial_leave_deduction_for_fulltime_shift(): void
    {
        // Cùng kịch bản bug đã tái hiện ở AttendanceLogsTest (Báo cáo chấm công): ca văn phòng
        // full-time 09:00-18:00, nghỉ nửa ngày 13:00-18:00 đã duyệt (day_fraction=0.56), đi làm
        // phần còn lại 09:07-13:00. Widget "Lịch làm việc của tôi" tổng hợp công RIÊNG (không
        // dùng chung ResolvesPartialLeaveIndex như trang Báo cáo chấm công) nên phải tự kiểm tra:
        // "công" trả về KHÔNG được tính đủ 1 (full-time "1 ca đủ vào/ra = 1 công" mặc định),
        // mà phải trừ đúng theo day_fraction giống computeCong(null, 0.56) = 0.44.
        $role = Role::where('name', 'staff')->first();
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-own-attendance']));

        $workDate = now()->toDateString();
        $shift = Shift::create([
            'code' => 'CA-VP-TEST', 'name' => 'Ca văn phòng', 'start_time' => '09:00', 'end_time' => '18:00',
            'break_minutes' => 60, 'work_mode' => 'onsite', 'shift_type' => 'fulltime',
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        LeaveRequest::create([
            'code' => 'LR-MYSCHED-01', 'employee_id' => $this->employee->id,
            'date_from' => $workDate, 'date_to' => $workDate, 'shift_schedule_id' => $schedule->id,
            'is_partial_day' => true, 'from_time' => '13:00', 'to_time' => '18:00', 'day_fraction' => 0.56,
            'type' => 'annual', 'reason' => 'Đi khám bệnh', 'status' => 'approved',
        ]);

        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $workDate,
            'check_in_at' => $workDate . ' 09:07:00', 'check_out_at' => $workDate . ' 13:00:00',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.attendance-summary', [
            'start' => now()->startOfMonth()->toDateString(),
            'end'   => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJson(['cong' => 0.44]);
    }

    public function test_index_forbidden_without_employee_record(): void
    {
        $noEmpUser = User::factory()->create();
        $noEmpUser->assignRole('staff');

        $response = $this->actingAs($noEmpUser)->get(route('my-schedule.index'));
        $response->assertStatus(403);
    }

    public function test_index_guest_redirected_to_login(): void
    {
        $response = $this->get(route('my-schedule.index'));
        $response->assertRedirect(route('login'));
    }

    // ── JSON feed cho FullCalendar ───────────────────────────────────────

    private function eventsRange(): array
    {
        return [
            'start' => now()->startOfMonth()->toDateString(),
            'end'   => now()->endOfMonth()->toDateString(),
        ];
    }

    public function test_events_feed_returns_own_shift(): void
    {
        $shift = Shift::create(['code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id,
            'work_date' => now()->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.events', $this->eventsRange()));

        $response->assertStatus(200);
        $response->assertJsonFragment(['start' => now()->toDateString()]);
        $this->assertStringContainsString('Ca hành chính', $response->getContent());
    }

    public function test_events_feed_does_not_include_other_employees_shift(): void
    {
        $otherUser = User::factory()->create();
        $otherEmployee = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'user_id' => $otherUser->id, 'is_active' => true]);
        $shift = Shift::create(['code' => 'CA-KHAC', 'name' => 'Ca của người khác', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        ShiftSchedule::create([
            'employee_id' => $otherEmployee->id, 'shift_id' => $shift->id,
            'work_date' => now()->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.events', $this->eventsRange()));

        $response->assertStatus(200);
        $this->assertStringNotContainsString('Ca của người khác', $response->getContent());
    }

    public function test_events_feed_excludes_cancelled_schedule(): void
    {
        $shift = Shift::create(['code' => 'CA-HUY', 'name' => 'Ca đã huỷ', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id,
            'work_date' => now()->toDateString(), 'status' => 'cancelled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.events', $this->eventsRange()));

        $response->assertStatus(200);
        $this->assertStringNotContainsString('Ca đã huỷ', $response->getContent());
    }

    public function test_events_feed_includes_approved_leave(): void
    {
        LeaveRequest::create([
            'code' => 'LR-TEST', 'employee_id' => $this->employee->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ', 'status' => 'approved',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.events', $this->eventsRange()));

        $response->assertStatus(200);
        $this->assertStringContainsString('Nghỉ phép năm', $response->getContent());
    }

    public function test_events_feed_excludes_pending_leave(): void
    {
        LeaveRequest::create([
            'code' => 'LR-TEST2', 'employee_id' => $this->employee->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'sick', 'reason' => 'Nghỉ ốm', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.events', $this->eventsRange()));

        $response->assertStatus(200);
        $this->assertStringNotContainsString('Nghỉ ốm', $response->getContent());
    }

    public function test_events_feed_marks_completed_attendance_with_late_minutes(): void
    {
        $shift = Shift::create(['code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id,
            'work_date' => now()->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'shift_schedule_id' => $schedule->id,
            'work_date' => now()->toDateString(),
            'check_in_at' => now()->setTime(8, 15), 'check_out_at' => now()->setTime(17, 0),
            'late_minutes' => 15, 'early_minutes' => 0,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.events', $this->eventsRange()));

        $response->assertStatus(200);
        $data = $response->json();
        $event = collect($data)->firstWhere('extendedProps.type', 'shift');
        $this->assertEquals('completed', $event['extendedProps']['attendanceStatus']);
        $this->assertEquals('08:15', $event['extendedProps']['checkInAt']);
        $this->assertEquals('17:00', $event['extendedProps']['checkOutAt']);
        $this->assertEquals(15, $event['extendedProps']['lateMinutes']);
    }

    public function test_events_feed_marks_in_progress_when_only_checked_in(): void
    {
        $shift = Shift::create(['code' => 'CA-HC2', 'name' => 'Ca hành chính 2', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id,
            'work_date' => now()->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'shift_schedule_id' => $schedule->id,
            'work_date' => now()->toDateString(), 'check_in_at' => now()->setTime(8, 0),
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.events', $this->eventsRange()));

        $data = $response->json();
        $event = collect($data)->firstWhere('extendedProps.type', 'shift');
        $this->assertEquals('in_progress', $event['extendedProps']['attendanceStatus']);
        $this->assertEquals('08:00', $event['extendedProps']['checkInAt']);
        $this->assertNull($event['extendedProps']['checkOutAt']);
    }

    public function test_events_feed_marks_missed_for_past_date_without_attendance(): void
    {
        $shift = Shift::create(['code' => 'CA-QK', 'name' => 'Ca quá khứ', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id,
            'work_date' => now()->subDays(2)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $range = ['start' => now()->subMonth()->toDateString(), 'end' => now()->addMonth()->toDateString()];
        $response = $this->actingAs($this->user)->getJson(route('my-schedule.events', $range));

        $data = $response->json();
        $event = collect($data)->firstWhere('extendedProps.type', 'shift');
        $this->assertEquals('missed', $event['extendedProps']['attendanceStatus']);
    }

    public function test_events_feed_marks_upcoming_for_future_date_without_attendance(): void
    {
        $shift = Shift::create(['code' => 'CA-TL', 'name' => 'Ca tương lai', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id,
            'work_date' => now()->addDays(2)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $range = ['start' => now()->toDateString(), 'end' => now()->addMonth()->toDateString()];
        $response = $this->actingAs($this->user)->getJson(route('my-schedule.events', $range));

        $data = $response->json();
        $event = collect($data)->firstWhere('extendedProps.type', 'shift');
        $this->assertEquals('upcoming', $event['extendedProps']['attendanceStatus']);
    }

    public function test_events_feed_respects_date_range(): void
    {
        $shift = Shift::create(['code' => 'CA-XA', 'name' => 'Ca xa', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        $farDate = now()->addMonths(6);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id,
            'work_date' => $farDate->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.events', $this->eventsRange()));

        $response->assertStatus(200);
        $this->assertStringNotContainsString('Ca xa', $response->getContent());
    }

    public function test_events_forbidden_without_employee_record(): void
    {
        $noEmpUser = User::factory()->create();
        $noEmpUser->assignRole('staff');

        $response = $this->actingAs($noEmpUser)->getJson(route('my-schedule.events', $this->eventsRange()));
        $response->assertStatus(403);
    }

    public function test_events_user_without_permission_forbidden(): void
    {
        $noPermUser = User::factory()->create();
        $response = $this->actingAs($noPermUser)->getJson(route('my-schedule.events', $this->eventsRange()));
        $response->assertStatus(403);
    }

    public function test_events_guest_redirected_to_login(): void
    {
        $response = $this->get(route('my-schedule.events', $this->eventsRange()));
        $response->assertRedirect(route('login'));
    }

    // ── Tổng hợp giờ làm/công của bản thân ───────────────────────────────

    public function test_employee_with_permission_can_view_own_attendance_summary(): void
    {
        $role = Role::where('name', 'staff')->first();
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-own-attendance']));

        $shift = Shift::create(['code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id,
            'work_date' => now()->startOfMonth()->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'shift_schedule_id' => $schedule->id,
            'work_date' => now()->startOfMonth()->toDateString(),
            'check_in_at' => now()->startOfMonth()->setTime(8, 0), 'check_out_at' => now()->startOfMonth()->setTime(17, 0),
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.attendance-summary', $this->eventsRange()));

        $response->assertStatus(200);
        $response->assertJson(['worked_hours' => 9.0, 'days_worked' => 1]);
    }

    public function test_employee_without_permission_cannot_view_own_attendance_summary(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('my-schedule.attendance-summary', $this->eventsRange()));
        $response->assertStatus(403);
    }

    public function test_own_attendance_summary_excludes_other_employees_logs(): void
    {
        $role = Role::where('name', 'staff')->first();
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-own-attendance']));

        $otherUser = User::factory()->create();
        $otherEmployee = Employee::create(['code' => 'EMP-03', 'name' => 'Người khác', 'user_id' => $otherUser->id, 'is_active' => true]);
        AttendanceLog::create([
            'employee_id' => $otherEmployee->id,
            'work_date' => now()->startOfMonth()->toDateString(),
            'check_in_at' => now()->startOfMonth()->setTime(8, 0), 'check_out_at' => now()->startOfMonth()->setTime(17, 0),
        ]);

        $response = $this->actingAs($this->user)->getJson(route('my-schedule.attendance-summary', $this->eventsRange()));

        $response->assertStatus(200);
        $response->assertJson(['worked_hours' => 0.0, 'days_worked' => 0]);
    }

    public function test_own_attendance_summary_guest_redirected_to_login(): void
    {
        $response = $this->get(route('my-schedule.attendance-summary', $this->eventsRange()));
        $response->assertRedirect(route('login'));
    }

    // ── Xuất Excel ───────────────────────────────────────────────────────

    public function test_employee_can_export_own_schedule(): void
    {
        $role = Role::where('name', 'staff')->first();
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'export-own-schedule']));

        $shift = Shift::create(['code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->user)->get(route('my-schedule.export', ['range_type' => 'week']));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_employee_without_export_permission_cannot_export_own_schedule(): void
    {
        $response = $this->actingAs($this->user)->get(route('my-schedule.export', ['range_type' => 'week']));
        $response->assertStatus(403);
    }
}
