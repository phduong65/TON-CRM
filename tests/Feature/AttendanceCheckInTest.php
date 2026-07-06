<?php

namespace Tests\Feature;

use App\Models\AttendanceLocation;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceCheckInTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;
    private Branch $branch;
    private AttendanceLocation $location;

    // Toạ độ văn phòng — Đà Nẵng (ví dụ)
    private const OFFICE_LAT = 16.0544;
    private const OFFICE_LNG = 108.2022;
    private const OFFICE_IP  = '203.0.113.10';

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->location = AttendanceLocation::create([
            'branch_id'     => $this->branch->id,
            'name'          => 'Văn phòng chính',
            'latitude'      => self::OFFICE_LAT,
            'longitude'     => self::OFFICE_LNG,
            'radius_meters' => 100,
            // '127.0.0.1' — IP mặc định của request trong test HTTP client — được thêm vào đây để
            // các test "happy path" (chỉ set toạ độ GPS đúng, không set REMOTE_ADDR) vẫn đạt điều
            // kiện IP, vì từ khi đổi sang yêu cầu CẢ HAI (GPS và IP) thì thiếu 1 trong 2 sẽ bị chặn.
            'allowed_ips'   => [self::OFFICE_IP, '127.0.0.1'],
            'is_active'     => true,
        ]);
    }

    private function makeOnsiteShiftToday(): Shift
    {
        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính',
            'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);

        ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $shift->id,
            'branch_id'   => $this->branch->id,
            'work_date'   => now()->toDateString(),
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        return $shift;
    }

    // ── Trang cá nhân ────────────────────────────────────────────────────

    public function test_attendance_index_page_renders_with_quick_access(): void
    {
        $response = $this->actingAs($this->user)->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertSee('Truy cập nhanh');
        $response->assertSee('Check-in', false);
    }

    // ── Happy paths ──────────────────────────────────────────────────────

    public function test_checkin_succeeds_within_gps_radius(): void
    {
        $this->makeOnsiteShiftToday();

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT,
            'lng' => self::OFFICE_LNG,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'work_date'   => now()->toDateString(),
        ]);
    }

    public function test_checkin_succeeds_when_both_gps_and_ip_match(): void
    {
        $this->makeOnsiteShiftToday();

        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => self::OFFICE_IP])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT,
                'lng' => self::OFFICE_LNG,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id'     => $this->employee->id,
            'check_in_method' => 'gps_ip',
        ]);
    }

    // Trước đây hệ thống chỉ cần đạt 1 trong 2 (GPS hoặc IP) — nay bắt buộc phải đạt CẢ HAI.

    public function test_checkin_blocked_when_ip_matches_but_gps_off(): void
    {
        $this->makeOnsiteShiftToday();

        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => self::OFFICE_IP])
            ->postJson(route('attendance.check-in'), [
                'lat' => 0, // toạ độ lệch hoàn toàn
                'lng' => 0,
            ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $this->employee->id]);
    }

    public function test_checkin_blocked_when_gps_matches_but_ip_wrong(): void
    {
        $this->makeOnsiteShiftToday();

        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT,
                'lng' => self::OFFICE_LNG,
            ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $this->employee->id]);
    }

    public function test_wfh_shift_bypasses_location_check(): void
    {
        $shift = Shift::create([
            'code' => 'CA-WFH', 'name' => 'Ca WFH',
            'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'wfh',
        ]);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $shift->id,
            'branch_id'   => $this->branch->id,
            'work_date'   => now()->toDateString(),
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->postJson(route('attendance.check-in'), ['lat' => 0, 'lng' => 0]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'check_in_method' => 'wfh',
        ]);
    }

    // ── Blocked paths ────────────────────────────────────────────────────

    public function test_checkin_blocked_outside_gps_and_wrong_ip(): void
    {
        $this->makeOnsiteShiftToday();

        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->postJson(route('attendance.check-in'), [
                'lat' => 0,
                'lng' => 0,
            ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $this->employee->id]);
    }

    // ── Idempotency ──────────────────────────────────────────────────────

    public function test_cannot_checkin_twice_same_day(): void
    {
        $this->makeOnsiteShiftToday();

        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ])->assertStatus(200);

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertEquals(1, \App\Models\AttendanceLog::where('employee_id', $this->employee->id)->count());
    }

    public function test_cannot_checkout_before_checkin(): void
    {
        $this->makeOnsiteShiftToday();

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-out'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_checkout_succeeds_after_checkin(): void
    {
        $this->makeOnsiteShiftToday();

        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ])->assertStatus(200);

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-out'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
        ]);
        $log = \App\Models\AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertNotNull($log->check_out_at);
    }

    // ── Thiết bị chấm công (check_in_device / check_out_device) ─────────

    public function test_checkout_flags_device_changed_when_user_agent_differs_from_checkin(): void
    {
        $this->makeOnsiteShiftToday();

        $this->actingAs($this->user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) AppleWebKit/605.1.15'])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ])->assertStatus(200);

        $response = $this->actingAs($this->user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
            ->postJson(route('attendance.check-out'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true, 'device_changed' => true]);
        $response->assertJsonFragment(['device_changed' => true]);

        $log = \App\Models\AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertTrue($log->deviceChanged());
        $this->assertNotEquals($log->check_in_device, $log->check_out_device);
    }

    public function test_checkout_does_not_flag_device_changed_when_same_user_agent(): void
    {
        $this->makeOnsiteShiftToday();
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

        $this->actingAs($this->user)
            ->withHeaders(['User-Agent' => $ua])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ])->assertStatus(200);

        $response = $this->actingAs($this->user)
            ->withHeaders(['User-Agent' => $ua])
            ->postJson(route('attendance.check-out'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true, 'device_changed' => false]);

        $log = \App\Models\AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertFalse($log->deviceChanged());
    }

    // ── Race condition (double-submit) ──────────────────────────────────

    public function test_sequential_double_checkin_only_creates_one_record(): void
    {
        $this->makeOnsiteShiftToday();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ]);
        }

        $this->assertEquals(1, \App\Models\AttendanceLog::where('employee_id', $this->employee->id)->count());
    }

    // ── Đa ca (nhiều ca cùng ngày) ───────────────────────────────────────

    public function test_checkin_requires_shift_selection_when_multiple_shifts_today(): void
    {
        $shiftA = $this->makeOnsiteShiftToday();
        $shiftB = Shift::create([
            'code' => 'CA-TOI', 'name' => 'Ca tối', 'start_time' => '18:00', 'end_time' => '22:00', 'work_mode' => 'onsite',
        ]);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $shiftB->id,
            'branch_id'   => $this->branch->id,
            'work_date'   => now()->toDateString(),
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_checkin_with_explicit_shift_schedule_id_creates_separate_logs(): void
    {
        $this->makeOnsiteShiftToday();
        $scheduleA = ShiftSchedule::where('employee_id', $this->employee->id)->first();

        $shiftB = Shift::create([
            'code' => 'CA-TOI', 'name' => 'Ca tối', 'start_time' => '18:00', 'end_time' => '22:00', 'work_mode' => 'onsite',
        ]);
        $scheduleB = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $shiftB->id,
            'branch_id'   => $this->branch->id,
            'work_date'   => now()->toDateString(),
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $scheduleA->id,
        ])->assertStatus(200);

        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $scheduleB->id,
        ])->assertStatus(200);

        $this->assertEquals(2, \App\Models\AttendanceLog::where('employee_id', $this->employee->id)->count());
        $this->assertDatabaseHas('attendance_logs', ['shift_schedule_id' => $scheduleA->id]);
        $this->assertDatabaseHas('attendance_logs', ['shift_schedule_id' => $scheduleB->id]);
    }

    // ── Authorization ────────────────────────────────────────────────────

    public function test_guest_redirected_to_login(): void
    {
        $response = $this->post(route('attendance.check-in'), ['lat' => 0, 'lng' => 0]);
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_forbidden(): void
    {
        $noPermUser = User::factory()->create();

        $response = $this->actingAs($noPermUser)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ]);

        $response->assertStatus(403);
    }
}
