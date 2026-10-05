<?php

namespace Tests\Feature;

use App\Models\AttendanceLocation;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceIpMismatchAlertTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private AttendanceLocation $location;
    private User $admin;
    private Shift $shift;

    // Toạ độ văn phòng — Đà Nẵng (ví dụ)
    private const OFFICE_LAT = 16.0544;
    private const OFFICE_LNG = 108.2022;
    private const OFFICE_IP  = '203.0.113.10';
    private const WRONG_IP   = '9.9.9.9';

    protected function setUp(): void
    {
        parent::setUp();

        // Cố định "bây giờ" = 09:00 hôm nay — ca dùng trong test là 08h-17h, tránh phụ thuộc
        // vào giờ chạy test thực tế từ khi có kiểm tra khung giờ check-in/out.
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => 'checkin-attendance']));

        $adminRole = Role::firstOrCreate(['name' => 'admin-locations']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'edit-attendance-locations']));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin-locations');

        $this->branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->location = AttendanceLocation::create([
            'branch_id'     => $this->branch->id,
            'name'          => 'Văn phòng chính',
            'latitude'      => self::OFFICE_LAT,
            'longitude'     => self::OFFICE_LNG,
            'radius_meters' => 100,
            'allowed_ips'   => [self::OFFICE_IP],
            'is_active'     => true,
        ]);

        $this->shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính',
            'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeEmployeeWithShiftToday(string $code): Employee
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        $employee = Employee::create([
            'code'      => $code,
            'name'      => 'NV ' . $code,
            'user_id'   => $user->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        ShiftSchedule::create([
            'employee_id' => $employee->id,
            'shift_id'    => $this->shift->id,
            'branch_id'   => $this->branch->id,
            'work_date'   => now()->toDateString(),
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        return $employee;
    }

    private function attemptGpsOkIpWrongCheckIn(Employee $employee): void
    {
        $this->actingAs($employee->user)
            ->withServerVariables(['REMOTE_ADDR' => self::WRONG_IP])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT,
                'lng' => self::OFFICE_LNG,
            ])->assertStatus(422);
    }

    private function successfulCheckIn(Employee $employee): void
    {
        $this->actingAs($employee->user)
            ->withServerVariables(['REMOTE_ADDR' => self::OFFICE_IP])
            ->postJson(route('attendance.check-in'), [
                'lat' => self::OFFICE_LAT,
                'lng' => self::OFFICE_LNG,
            ])->assertStatus(200);
    }

    public function test_records_ip_mismatch_row_when_gps_ok_but_ip_wrong(): void
    {
        $employee = $this->makeEmployeeWithShiftToday('EMP-01');

        $this->attemptGpsOkIpWrongCheckIn($employee);

        $this->assertDatabaseHas('attendance_ip_mismatches', [
            'attendance_location_id' => $this->location->id,
            'employee_id'            => $employee->id,
            'ip'                     => self::WRONG_IP,
        ]);
    }

    public function test_does_not_record_mismatch_when_ip_is_correct_but_gps_off(): void
    {
        $employee = $this->makeEmployeeWithShiftToday('EMP-01');

        $this->actingAs($employee->user)
            ->withServerVariables(['REMOTE_ADDR' => self::OFFICE_IP])
            ->postJson(route('attendance.check-in'), ['lat' => 0, 'lng' => 0])
            ->assertStatus(422);

        $this->assertDatabaseCount('attendance_ip_mismatches', 0);
    }

    public function test_no_alert_below_distinct_employee_threshold(): void
    {
        $e1 = $this->makeEmployeeWithShiftToday('EMP-01');
        $e2 = $this->makeEmployeeWithShiftToday('EMP-02');

        $this->attemptGpsOkIpWrongCheckIn($e1);
        $this->attemptGpsOkIpWrongCheckIn($e2);

        $this->assertDatabaseMissing('notifications', ['type' => 'attendance_ip_mismatch']);
    }

    public function test_same_employee_retrying_does_not_inflate_distinct_count(): void
    {
        $employee = $this->makeEmployeeWithShiftToday('EMP-01');

        for ($i = 0; $i < 5; $i++) {
            $this->attemptGpsOkIpWrongCheckIn($employee);
        }

        $this->assertDatabaseMissing('notifications', ['type' => 'attendance_ip_mismatch']);
    }

    public function test_alerts_admin_once_distinct_employee_threshold_reached(): void
    {
        $e1 = $this->makeEmployeeWithShiftToday('EMP-01');
        $e2 = $this->makeEmployeeWithShiftToday('EMP-02');
        $e3 = $this->makeEmployeeWithShiftToday('EMP-03');

        $this->attemptGpsOkIpWrongCheckIn($e1);
        $this->attemptGpsOkIpWrongCheckIn($e2);
        $this->attemptGpsOkIpWrongCheckIn($e3);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin->id,
            'type'    => 'attendance_ip_mismatch',
        ]);

        $notification = Notification::where('type', 'attendance_ip_mismatch')->where('user_id', $this->admin->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals($this->location->id, $notification->data['attendance_location_id']);
        $this->assertStringContainsString(self::WRONG_IP, $notification->body);

        $this->location->refresh();
        $this->assertNotNull($this->location->ip_mismatch_alerted_at);
    }

    public function test_does_not_alert_twice_same_day(): void
    {
        $e1 = $this->makeEmployeeWithShiftToday('EMP-01');
        $e2 = $this->makeEmployeeWithShiftToday('EMP-02');
        $e3 = $this->makeEmployeeWithShiftToday('EMP-03');
        $e4 = $this->makeEmployeeWithShiftToday('EMP-04');

        $this->attemptGpsOkIpWrongCheckIn($e1);
        $this->attemptGpsOkIpWrongCheckIn($e2);
        $this->attemptGpsOkIpWrongCheckIn($e3);

        $this->assertEquals(1, Notification::where('type', 'attendance_ip_mismatch')->count());

        $this->attemptGpsOkIpWrongCheckIn($e4);

        $this->assertEquals(1, Notification::where('type', 'attendance_ip_mismatch')->count());
    }

    public function test_successful_gps_ip_check_in_clears_alert_and_todays_mismatch_rows(): void
    {
        $e1 = $this->makeEmployeeWithShiftToday('EMP-01');
        $e2 = $this->makeEmployeeWithShiftToday('EMP-02');
        $e3 = $this->makeEmployeeWithShiftToday('EMP-03');
        $e4 = $this->makeEmployeeWithShiftToday('EMP-04'); // admin đã sửa allowed_ips, NV này chấm công đúng

        $this->attemptGpsOkIpWrongCheckIn($e1);
        $this->attemptGpsOkIpWrongCheckIn($e2);
        $this->attemptGpsOkIpWrongCheckIn($e3);

        $this->location->refresh();
        $this->assertNotNull($this->location->ip_mismatch_alerted_at);
        $this->assertDatabaseCount('attendance_ip_mismatches', 3);

        $this->successfulCheckIn($e4);

        $this->location->refresh();
        $this->assertNull($this->location->ip_mismatch_alerted_at);
        $this->assertDatabaseCount('attendance_ip_mismatches', 0);
    }

    public function test_alerts_again_same_day_after_resolution_and_new_mismatch_batch(): void
    {
        $e1 = $this->makeEmployeeWithShiftToday('EMP-01');
        $e2 = $this->makeEmployeeWithShiftToday('EMP-02');
        $e3 = $this->makeEmployeeWithShiftToday('EMP-03');
        $fixedEmployee = $this->makeEmployeeWithShiftToday('EMP-04');
        $e5 = $this->makeEmployeeWithShiftToday('EMP-05');
        $e6 = $this->makeEmployeeWithShiftToday('EMP-06');
        $e7 = $this->makeEmployeeWithShiftToday('EMP-07');

        // Đợt sự cố 1 — đủ ngưỡng, cảnh báo lần 1.
        $this->attemptGpsOkIpWrongCheckIn($e1);
        $this->attemptGpsOkIpWrongCheckIn($e2);
        $this->attemptGpsOkIpWrongCheckIn($e3);
        $this->assertEquals(1, Notification::where('type', 'attendance_ip_mismatch')->count());

        // Admin sửa allowed_ips giữa ngày — NV chấm công thành công xác nhận sự cố đã hết.
        $this->successfulCheckIn($fixedEmployee);

        // Đợt sự cố 2 (VD IP văn phòng đổi lần nữa trong ngày) — vẫn phải đủ ngưỡng NV khác nhau
        // MỚI mới cảnh báo lại, và phải cảnh báo lại vì cooldown của sự cố cũ đã được xoá.
        $this->attemptGpsOkIpWrongCheckIn($e5);
        $this->attemptGpsOkIpWrongCheckIn($e6);
        $this->assertEquals(1, Notification::where('type', 'attendance_ip_mismatch')->count());

        $this->attemptGpsOkIpWrongCheckIn($e7);
        $this->assertEquals(2, Notification::where('type', 'attendance_ip_mismatch')->count());
    }

    public function test_successful_check_in_does_not_touch_location_when_no_active_alert(): void
    {
        $e1 = $this->makeEmployeeWithShiftToday('EMP-01');

        $this->successfulCheckIn($e1);

        $this->location->refresh();
        $this->assertNull($this->location->ip_mismatch_alerted_at);
    }

    public function test_user_without_permission_is_not_notified(): void
    {
        $bystander = User::factory()->create(); // không có quyền edit-attendance-locations

        $e1 = $this->makeEmployeeWithShiftToday('EMP-01');
        $e2 = $this->makeEmployeeWithShiftToday('EMP-02');
        $e3 = $this->makeEmployeeWithShiftToday('EMP-03');

        $this->attemptGpsOkIpWrongCheckIn($e1);
        $this->attemptGpsOkIpWrongCheckIn($e2);
        $this->attemptGpsOkIpWrongCheckIn($e3);

        $this->assertDatabaseMissing('notifications', ['user_id' => $bystander->id, 'type' => 'attendance_ip_mismatch']);
    }
}
