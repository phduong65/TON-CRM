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
