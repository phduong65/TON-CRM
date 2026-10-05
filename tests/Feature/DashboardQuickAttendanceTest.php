<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardQuickAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'staff']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'checkin-attendance']));

        $this->user = User::factory()->create();
        $this->user->assignRole('staff');

        $this->branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->employee = Employee::create([
            'code'      => 'EMP-01',
            'name'      => 'Nguyễn Văn A',
            'user_id'   => $this->user->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeOnsiteShiftToday(): Shift
    {
        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính',
            'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);

        ShiftSchedule::create([
            'employee_id'      => $this->employee->id,
            'shift_id'         => $shift->id,
            'branch_id'        => $this->branch->id,
            'work_date'        => now()->toDateString(),
            'assignment_type'  => 'rotation',
            'status'           => 'scheduled',
        ]);

        return $shift;
    }

    public function test_employee_with_permission_sees_quick_attendance_widget_on_dashboard(): void
    {
        $this->makeOnsiteShiftToday();

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Chấm công nhanh');
        $response->assertSee('Ca hành chính');
        $response->assertSee('Check-in');
        $response->assertSee('Check-out');
    }

    public function test_checkout_button_is_disabled_before_checkin(): void
    {
        $this->makeOnsiteShiftToday();

        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertOk();

        $this->assertMatchesRegularExpression(
            '/id="btnCheckOut-\d+"[^>]*disabled/',
            $response->getContent(),
            'Nút Check-out phải bị disabled khi chưa check-in.'
        );
    }

    public function test_checkout_button_becomes_enabled_after_checkin(): void
    {
        $this->makeOnsiteShiftToday();
        $schedule = ShiftSchedule::where('employee_id', $this->employee->id)->firstOrFail();

        \App\Models\AttendanceLog::create([
            'employee_id'       => $this->employee->id,
            'shift_schedule_id' => $schedule->id,
            'work_date'         => now()->toDateString(),
            'check_in_at'       => now(),
            'check_in_method'   => 'gps_ip',
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertOk();

        $response->assertSee('Đã check-in');
        $this->assertDoesNotMatchRegularExpression(
            '/id="btnCheckOut-\d+"[^>]*disabled/',
            $response->getContent(),
            'Nút Check-out phải được bật ngay sau khi đã check-in.'
        );
    }

    public function test_employee_without_permission_does_not_see_quick_attendance_widget(): void
    {
        $this->user->syncRoles([]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Chấm công nhanh');
    }

    public function test_employee_without_shift_today_sees_unscheduled_quick_checkin(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Chấm công nhanh');
        $response->assertSee('Ca ngoài lịch');
    }

    public function test_missed_shift_hides_checkin_button_on_dashboard_widget(): void
    {
        $shift = Shift::create([
            'code' => 'CA-HC2', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '09:00',
            'work_mode' => 'onsite',
        ]);
        ShiftSchedule::create([
            'employee_id'      => $this->employee->id,
            'shift_id'         => $shift->id,
            'branch_id'        => $this->branch->id,
            'work_date'        => now()->toDateString(),
            'assignment_type'  => 'rotation',
            'status'           => 'scheduled',
        ]);

        // Quá giờ kết thúc ca (09h) mà chưa check-in.
        Carbon::setTestNow(Carbon::today()->setTime(9, 30));

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('id="btnCheckIn-');
        $response->assertSee('đã kết thúc');
    }

    public function test_both_buttons_show_done_state_after_checkout(): void
    {
        $this->makeOnsiteShiftToday();
        $schedule = ShiftSchedule::where('employee_id', $this->employee->id)->firstOrFail();

        // Bỏ qua xác thực GPS/IP thật — chỉ cần xác nhận UI phản ánh đúng trạng thái sau khi có
        // AttendanceLog (luồng check-in/out thật đã được test đầy đủ ở AttendanceCheckInTest).
        \App\Models\AttendanceLog::create([
            'employee_id'       => $this->employee->id,
            'shift_schedule_id' => $schedule->id,
            'work_date'         => now()->toDateString(),
            'check_in_at'       => now(),
            'check_out_at'      => now()->addHours(8),
            'check_in_method'   => 'gps_ip',
            'check_out_method'  => 'gps_ip',
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Đã check-in');
        $response->assertSee('Đã check-out');
        $response->assertDontSee('id="btnCheckIn-');
        $response->assertDontSee('id="btnCheckOut-');
    }
}
