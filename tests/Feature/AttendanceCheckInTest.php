<?php

namespace Tests\Feature;

use App\Models\AttendanceLocation;
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

        // Cố định "bây giờ" = 09:00 hôm nay để các test check-in/out không phụ thuộc vào giờ chạy
        // test thực tế.
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

    public function test_checkout_does_not_flag_device_changed_when_same_phone_different_browser(): void
    {
        // Safari và Chrome (CriOS) trên CÙNG 1 iPhone — User-Agent khác nhau ở phần trình
        // duyệt nhưng cùng phần thiết bị/OS "(iPhone; CPU iPhone OS 17_0 like Mac OS X)".
        $this->makeOnsiteShiftToday();

        $this->actingAs($this->user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ])->assertStatus(200);

        $response = $this->actingAs($this->user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/119.0.6045.109 Mobile/15E148 Safari/604.1'])
            ->postJson(route('attendance.check-out'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true, 'device_changed' => false]);

        $log = \App\Models\AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertFalse($log->deviceChanged());
        $this->assertNotEquals($log->check_in_device, $log->check_out_device);
    }

    public function test_checkout_still_flags_device_changed_when_genuinely_different_phone(): void
    {
        // Cùng "họ" trình duyệt (Chrome) nhưng khác hẳn dòng máy/hệ điều hành — vẫn phải cảnh báo.
        $this->makeOnsiteShiftToday();

        $this->actingAs($this->user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36'])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ])->assertStatus(200);

        $response = $this->actingAs($this->user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/119.0.6045.109 Mobile/15E148 Safari/604.1'])
            ->postJson(route('attendance.check-out'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true, 'device_changed' => true]);

        $log = \App\Models\AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertTrue($log->deviceChanged());
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

        // Ca A (08h-17h) check-in lúc 09h — trong khung giờ.
        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $scheduleA->id,
        ])->assertStatus(200);

        // Ca B (18h-22h) chỉ check-in được khi đã tới khung giờ của ca đó.
        Carbon::setTestNow(Carbon::today()->setTime(18, 15));

        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $scheduleB->id,
        ])->assertStatus(200);

        $this->assertEquals(2, \App\Models\AttendanceLog::where('employee_id', $this->employee->id)->count());
        $this->assertDatabaseHas('attendance_logs', ['shift_schedule_id' => $scheduleA->id]);
        $this->assertDatabaseHas('attendance_logs', ['shift_schedule_id' => $scheduleB->id]);
    }

    // ── Không còn giới hạn khung giờ check-in/out ──────────────────────────
    // Trước đây shifts.early_checkin_minutes/late_checkout_minutes chặn hẳn check-in/out ngoài
    // khung giờ ca. Tính năng này đã bị bỏ (xem migration
    // 2026_07_16_000001_drop_checkin_window_minutes_from_shifts_table.php) — các test dưới đây
    // xác nhận check-in/out vẫn thành công dù rất sớm hoặc rất trễ so với giờ ca.

    public function test_checkin_succeeds_even_long_before_shift_start(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(10, 40));

        $farShift = Shift::create([
            'code' => 'CA-XA', 'name' => 'Ca 18h-24h',
            'start_time' => '18:00', 'end_time' => '00:00', 'is_overnight' => true, 'work_mode' => 'onsite',
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $farShift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('attendance_logs', ['shift_schedule_id' => $schedule->id]);
    }

    public function test_checkin_rejected_after_shift_already_ended(): void
    {
        // Trước đây (khi bỏ early_checkin_minutes/late_checkout_minutes) hành vi này được phép —
        // nay bị chặn lại theo ShiftSchedule::isMissed(): quá giờ kết thúc ca mà chưa từng
        // check-in thì coi là "đã bỏ lỡ", không cho check-in trễ tuỳ ý nữa. Xem thêm
        // test_checkin_rejected_for_missed_shift().
        $shift = Shift::create([
            'code' => 'CA-TRE', 'name' => 'Ca 11h-15h', 'start_time' => '11:00', 'end_time' => '15:00',
            'work_mode' => 'onsite',
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(15, 30)); // ca đã kết thúc lúc 15h

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('attendance_logs', ['shift_schedule_id' => $schedule->id]);
    }

    public function test_checkout_succeeds_even_long_after_shift_ended(): void
    {
        $shift = Shift::create([
            'code' => 'CA-TRE2', 'name' => 'Ca 11h-15h', 'start_time' => '11:00', 'end_time' => '15:00',
            'work_mode' => 'onsite',
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(11, 5));
        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ])->assertStatus(200);

        Carbon::setTestNow(Carbon::today()->setTime(19, 45)); // rất trễ so với giờ kết thúc 15h

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-out'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $log = \App\Models\AttendanceLog::where('shift_schedule_id', $schedule->id)->first();
        $this->assertNotNull($log->check_out_at);
    }

    // ── Ca qua đêm — check-out sau khi đã sang ngày mới (mốc 3h sáng) ──────

    public function test_overnight_shift_checkin_before_midnight_then_checkout_after_midnight_succeeds(): void
    {
        $shift = Shift::create([
            'code' => 'CA-DEM', 'name' => 'Ca đêm', 'start_time' => '22:00', 'end_time' => '06:00',
            'is_overnight' => true, 'work_mode' => 'onsite',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(22, 0));
        $workDate = now()->toDateString();

        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => $workDate, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        // Check-in lúc 22h10 hôm trước.
        Carbon::setTestNow(Carbon::today()->setTime(22, 10));
        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ])->assertStatus(200)->assertJson(['success' => true]);

        // Check-out lúc 01h sáng hôm sau (đã qua nửa đêm, trước 3h sáng) — phải thành công.
        Carbon::setTestNow(Carbon::today()->addDay()->setTime(1, 0));
        $response = $this->actingAs($this->user)->postJson(route('attendance.check-out'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Log check-in/out phải cùng 1 bản ghi, work_date = ngày bắt đầu ca (hôm trước), không
        // phải "hôm nay" tại thời điểm check-out.
        $this->assertEquals(1, \App\Models\AttendanceLog::where('shift_schedule_id', $schedule->id)->count());
        $log = \App\Models\AttendanceLog::where('shift_schedule_id', $schedule->id)->first();
        $this->assertEquals($workDate, $log->work_date->toDateString());
        $this->assertNotNull($log->check_in_at);
        $this->assertNotNull($log->check_out_at);
    }

    public function test_overnight_shift_late_checkin_after_midnight_computes_correct_late_minutes(): void
    {
        // Ca 22h-06h, ân hạn trễ 10 phút. Check-in lúc 00h20 hôm sau — trễ 2h20 (140 phút) so với
        // giờ vào ca 22h, trừ 10 phút ân hạn = 130 phút trễ. Trước đây tính theo now()'s date khiến
        // "start" bị đẩy sang TƯƠNG LAI (22h cùng ngày hiện tại) nên luôn ra 0 phút trễ — sai.
        $shift = Shift::create([
            'code' => 'CA-DEM-TRE', 'name' => 'Ca đêm', 'start_time' => '22:00', 'end_time' => '06:00',
            'is_overnight' => true, 'work_mode' => 'onsite', 'grace_late_minutes' => 10,
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(22, 0));
        $workDate = now()->toDateString();
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => $workDate, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        Carbon::setTestNow(Carbon::today()->addDay()->setTime(0, 20));
        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ])->assertStatus(200)->assertJson(['success' => true]);

        $log = \App\Models\AttendanceLog::where('shift_schedule_id', $schedule->id)->first();
        $this->assertEquals(130, $log->late_minutes);
    }

    public function test_overnight_shift_early_checkout_before_midnight_computes_correct_early_minutes(): void
    {
        // Ca 22h-06h, ân hạn về sớm 10 phút. Check-out lúc 23h00 CÙNG ngày (trước nửa đêm) — sớm
        // 7 tiếng (420 phút) so với giờ ra ca 06h hôm sau, trừ 10 phút ân hạn = 410 phút sớm.
        // Trước đây "end" bị tính trên CÙNG ngày với check-out (06h cùng ngày, đã qua) nên
        // now() >= end luôn đúng, ra 0 phút sớm — sai.
        $shift = Shift::create([
            'code' => 'CA-DEM-SOM', 'name' => 'Ca đêm', 'start_time' => '22:00', 'end_time' => '06:00',
            'is_overnight' => true, 'work_mode' => 'onsite', 'grace_early_minutes' => 10,
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(22, 0));
        $workDate = now()->toDateString();
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => $workDate, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ])->assertStatus(200);

        Carbon::setTestNow(Carbon::today()->setTime(23, 0));
        $this->actingAs($this->user)->postJson(route('attendance.check-out'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ])->assertStatus(200)->assertJson(['success' => true]);

        $log = \App\Models\AttendanceLog::where('shift_schedule_id', $schedule->id)->first();
        $this->assertEquals(410, $log->early_minutes);
    }

    public function test_overnight_shift_still_listed_on_index_page_before_3am_cutoff(): void
    {
        $shift = Shift::create([
            'code' => 'CA-DEM2', 'name' => 'Ca đêm', 'start_time' => '22:00', 'end_time' => '06:00',
            'is_overnight' => true, 'work_mode' => 'onsite',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(22, 0));
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        // 02h sáng hôm sau — trước mốc 3h, ca qua đêm hôm qua vẫn phải hiển thị để check-out.
        Carbon::setTestNow(Carbon::today()->addDay()->setTime(2, 0));

        $response = $this->actingAs($this->user)->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertViewHas('activeShifts', function ($schedules) use ($schedule) {
            return $schedules->contains('id', $schedule->id);
        });
    }

    public function test_overnight_shift_no_longer_listed_on_index_page_after_3am_cutoff(): void
    {
        $shift = Shift::create([
            'code' => 'CA-DEM3', 'name' => 'Ca đêm', 'start_time' => '22:00', 'end_time' => '06:00',
            'is_overnight' => true, 'work_mode' => 'onsite',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(22, 0));
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        // 03h05 sáng hôm sau — đã qua mốc 3h, chỉ còn ca của "hôm nay" (không có).
        Carbon::setTestNow(Carbon::today()->addDay()->setTime(3, 5));

        $response = $this->actingAs($this->user)->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertViewHas('activeShifts', function ($schedules) use ($schedule) {
            return !$schedules->contains('id', $schedule->id);
        });
        $response->assertViewHas('missedShifts', function ($schedules) use ($schedule) {
            return !$schedules->contains('id', $schedule->id);
        });
    }

    public function test_non_overnight_shift_checked_in_but_not_out_stays_listed_after_midnight_and_can_checkout(): void
    {
        $shift = Shift::create([
            'code' => 'CA-TOI', 'name' => 'Ca Bar Tối', 'start_time' => '18:00', 'end_time' => '23:00',
            'work_mode' => 'onsite',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(18, 0));
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'fixed', 'status' => 'scheduled',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(18, 5));
        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ])->assertStatus(200);

        // 00:13 hôm sau — ca không qua đêm đã check-in nhưng chưa check-out vẫn phải hiện và check-out được.
        Carbon::setTestNow(Carbon::today()->addDay()->setTime(0, 13));

        $this->actingAs($this->user)->get(route('attendance.index'))
            ->assertViewHas('activeShifts', fn ($s) => $s->contains('id', $schedule->id));

        $this->actingAs($this->user)->postJson(route('attendance.check-out'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ])->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_checked_in_shift_stays_listed_past_3am_until_checked_out(): void
    {
        $shift = Shift::create([
            'code' => 'CA-DEM4', 'name' => 'Ca đêm', 'start_time' => '22:00', 'end_time' => '06:00',
            'is_overnight' => true, 'work_mode' => 'onsite',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(22, 0));
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);
        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ])->assertStatus(200);

        Carbon::setTestNow(Carbon::today()->addDay()->setTime(3, 5));

        $this->actingAs($this->user)->get(route('attendance.index'))
            ->assertViewHas('activeShifts', fn ($s) => $s->contains('id', $schedule->id));
    }

    // ── Ngưỡng cảnh báo ra ca sớm (earlyCheckoutBoundary) ───────────────────
    // Chỉ còn cảnh báo "ra ca sớm" dựa trên grace_early_minutes (kỷ luật) — KHÔNG còn cảnh báo
    // check-in sớm / check-out trễ vì đã bỏ early_checkin_minutes/late_checkout_minutes.

    public function test_index_page_renders_early_checkout_warning_boundary(): void
    {
        // Ca 11h-15h, cho phép về sớm không bị kỷ luật 10' (cảnh báo "ra ca sớm" chỉ khi trước 14h50).
        $shift = Shift::create([
            'code' => 'CA-NGUONG', 'name' => 'Ca 11h-15h', 'start_time' => '11:00', 'end_time' => '15:00',
            'work_mode' => 'onsite', 'grace_early_minutes' => 10,
        ]);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->user)->get(route('attendance.index'));

        // Tính đúng chuỗi mong đợi bằng chính Js::from() (dùng JSON.parse('...') + \uXXXX) thay vì
        // tự gõ tay ký tự escape — tránh sai lệch định dạng thực tế do Blade/Illuminate\Support\Js render.
        $expected = \Illuminate\Support\Js::from([
            'startTime'             => '11:00',
            'endTime'               => '15:00',
            'earlyCheckoutBoundary' => '14:50',
        ]);

        $response->assertStatus(200);
        $response->assertSee(e($expected), false);
    }

    public function test_index_page_omits_boundaries_for_overnight_shift(): void
    {
        $shift = Shift::create([
            'code' => 'CA-DEM4', 'name' => 'Ca đêm', 'start_time' => '22:00', 'end_time' => '06:00',
            'is_overnight' => true, 'work_mode' => 'onsite', 'grace_early_minutes' => 10,
        ]);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->user)->get(route('attendance.index'));

        $response->assertStatus(200);
        // Ca qua đêm không có mốc giờ ($checkBoundaries rỗng) — trang không được chứa object
        // boundaries có key "earlyCheckoutBoundary" với giá trị cụ thể (dấu hiệu bị gán nhầm mốc giờ).
        $response->assertDontSee('&quot;earlyCheckoutBoundary&quot;:&quot;', false);
    }

    // ── Ca đã bỏ lỡ (quá giờ kết thúc mà chưa từng check-in) ────────────────

    public function test_shift_past_end_time_without_checkin_moves_to_missed_shifts(): void
    {
        $shift = Shift::create([
            'code' => 'CA-HC5', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '09:00',
            'work_mode' => 'onsite',
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        // Đang trong ca (09h < 09h... test setUp mặc định 09:00) — set lại giờ để đảm bảo đang
        // trong ca trước, xác nhận nằm ở activeShifts.
        Carbon::setTestNow(Carbon::today()->setTime(8, 30));
        $response = $this->actingAs($this->user)->get(route('attendance.index'));
        $response->assertViewHas('activeShifts', fn($schedules) => $schedules->contains('id', $schedule->id));
        $response->assertViewHas('missedShifts', fn($schedules) => $schedules->isEmpty());

        // Quá giờ kết thúc ca (09h) mà chưa check-in — chuyển sang missedShifts.
        Carbon::setTestNow(Carbon::today()->setTime(9, 30));
        $response = $this->actingAs($this->user)->get(route('attendance.index'));
        $response->assertViewHas('activeShifts', fn($schedules) => $schedules->isEmpty());
        $response->assertViewHas('missedShifts', fn($schedules) => $schedules->contains('id', $schedule->id));
        $response->assertSee('Ca đã bỏ lỡ');
    }

    public function test_checkin_rejected_for_missed_shift(): void
    {
        $shift = Shift::create([
            'code' => 'CA-HC6', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '09:00',
            'work_mode' => 'onsite',
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        // Quá giờ kết thúc ca (09h) khá lâu — vẫn chưa check-in.
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertNull(\App\Models\AttendanceLog::where('shift_schedule_id', $schedule->id)->first());
    }

    public function test_checkout_still_allowed_after_shift_end_when_already_checked_in(): void
    {
        $shift = Shift::create([
            'code' => 'CA-HC7', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '09:00',
            'work_mode' => 'onsite',
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        // Check-in đúng giờ (08:30, trong ca).
        Carbon::setTestNow(Carbon::today()->setTime(8, 30));
        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ])->assertStatus(200);

        // Nhân viên quên check-out, giờ đã 12h — ca "kết thúc" từ lâu nhưng KHÔNG bị coi là "bỏ
        // lỡ" (đã check-in) nên vẫn ở activeShifts và check-out vẫn phải hoạt động bình thường.
        Carbon::setTestNow(Carbon::today()->setTime(12, 0));

        $response = $this->actingAs($this->user)->get(route('attendance.index'));
        $response->assertViewHas('activeShifts', fn($schedules) => $schedules->contains('id', $schedule->id));
        $response->assertViewHas('missedShifts', fn($schedules) => $schedules->isEmpty());

        $checkoutResponse = $this->actingAs($this->user)->postJson(route('attendance.check-out'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG, 'shift_schedule_id' => $schedule->id,
        ]);
        $checkoutResponse->assertStatus(200)->assertJson(['success' => true]);
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
