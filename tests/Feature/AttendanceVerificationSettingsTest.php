<?php

namespace Tests\Feature;

use App\Models\AttendanceLocation;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cấu hình settings.attendance_verify_gps/attendance_verify_wifi (SettingsController) quyết định
 * AttendanceController::resolveCheckMethod() kiểm tra GPS, WiFi, cả hai, hay bỏ qua xác thực vị
 * trí. Mặc định (chưa có row trong settings) tương đương bật cả hai — giữ đúng hành vi cũ.
 */
class AttendanceVerificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;
    private Branch $branch;

    private const OFFICE_LAT = 16.0544;
    private const OFFICE_LNG = 108.2022;
    private const OFFICE_IP  = '203.0.113.10';
    private const WRONG_LAT  = 0.0;
    private const WRONG_LNG  = 0.0;
    private const WRONG_IP   = '1.2.3.4';

    protected function setUp(): void
    {
        parent::setUp();

        // Ca test là 08:00-17:00 — cố định giờ hệ thống trong khung ca đang diễn ra, tránh test
        // flaky theo giờ chạy thật (ShiftSchedule::isMissed() sẽ chặn check-in nếu chạy sau 17h).
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

        AttendanceLocation::create([
            'branch_id'     => $this->branch->id,
            'name'          => 'Văn phòng chính',
            'latitude'      => self::OFFICE_LAT,
            'longitude'     => self::OFFICE_LNG,
            'radius_meters' => 100,
            'allowed_ips'   => [self::OFFICE_IP],
            'is_active'     => true,
        ]);

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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_gps_only_mode_allows_checkin_with_correct_gps_and_wrong_ip(): void
    {
        Setting::setValue('attendance_verify_gps', '1');
        Setting::setValue('attendance_verify_wifi', '0');

        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => self::WRONG_IP])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id'     => $this->employee->id,
            'check_in_method' => 'gps',
        ]);
    }

    public function test_gps_only_mode_blocks_checkin_with_wrong_gps(): void
    {
        Setting::setValue('attendance_verify_gps', '1');
        Setting::setValue('attendance_verify_wifi', '0');

        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => self::OFFICE_IP])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::WRONG_LAT, 'lng' => self::WRONG_LNG,
            ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $this->employee->id]);
    }

    public function test_wifi_only_mode_allows_checkin_with_correct_ip_and_wrong_gps(): void
    {
        Setting::setValue('attendance_verify_gps', '0');
        Setting::setValue('attendance_verify_wifi', '1');

        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => self::OFFICE_IP])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::WRONG_LAT, 'lng' => self::WRONG_LNG,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id'     => $this->employee->id,
            'check_in_method' => 'ip',
        ]);
    }

    public function test_wifi_only_mode_blocks_checkin_with_wrong_ip(): void
    {
        Setting::setValue('attendance_verify_gps', '0');
        Setting::setValue('attendance_verify_wifi', '1');

        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => self::WRONG_IP])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $this->employee->id]);
    }

    public function test_both_disabled_skips_location_verification_entirely(): void
    {
        Setting::setValue('attendance_verify_gps', '0');
        Setting::setValue('attendance_verify_wifi', '0');

        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => self::WRONG_IP])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::WRONG_LAT, 'lng' => self::WRONG_LNG,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id'     => $this->employee->id,
            'check_in_method' => 'skip',
        ]);
    }

    public function test_default_behavior_without_settings_rows_requires_both_gps_and_ip(): void
    {
        // Không set Setting nào — mặc định phải giữ đúng hành vi cũ (bắt buộc cả hai).
        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => self::WRONG_IP])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
            ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $this->employee->id]);
    }

    public function test_admin_can_update_verification_settings(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-settings']));

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('settings.update'), [
            'settings' => [
                'attendance_verify_gps'  => '0',
                'attendance_verify_wifi' => '1',
            ],
        ]);

        $response->assertRedirect();
        $this->assertEquals('0', Setting::getValue('attendance_verify_gps'));
        $this->assertEquals('1', Setting::getValue('attendance_verify_wifi'));
    }
}
