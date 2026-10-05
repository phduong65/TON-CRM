<?php

namespace Tests\Feature\Api;

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

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;
    private Branch $branch;
    private string $token;

    private const OFFICE_LAT = 16.0544;
    private const OFFICE_LNG = 108.2022;
    private const OFFICE_IP  = '203.0.113.10';

    protected function setUp(): void
    {
        parent::setUp();

        // Cố định "bây giờ" = 09:00 hôm nay — ca dùng trong test là 08h-17h, tránh phụ thuộc vào
        // giờ chạy test thực tế từ khi có kiểm tra khung giờ check-in/out (xem AttendanceCheckInTest).
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'staff']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'checkin-attendance']));
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-own-attendance']));

        $this->user = User::factory()->create(['status' => 'active']);
        $this->user->assignRole('staff');
        $this->token = $this->user->createToken('test')->plainTextToken;

        $this->branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A',
            'user_id' => $this->user->id, 'branch_id' => $this->branch->id, 'is_active' => true,
        ]);

        AttendanceLocation::create([
            'branch_id' => $this->branch->id, 'name' => 'Văn phòng chính',
            'latitude' => self::OFFICE_LAT, 'longitude' => self::OFFICE_LNG, 'radius_meters' => 100,
            'allowed_ips' => [self::OFFICE_IP, '127.0.0.1'], 'is_active' => true,
        ]);

        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính',
            'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function auth()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_today_endpoint_lists_shift_for_mobile(): void
    {
        $response = $this->auth()->getJson('/api/attendance/today');

        $response->assertOk();
        $this->assertCount(1, $response->json('shift_schedules'));
    }

    public function test_today_endpoint_resolves_flexible_shift_hours(): void
    {
        // Ca linh hoạt: không có shift_id, giờ lấy từ custom_start_time/custom_end_time.
        ShiftSchedule::where('employee_id', $this->employee->id)->delete();
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => null, 'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
            'custom_start_time' => '09:00', 'custom_end_time' => '18:00',
        ]);

        $response = $this->auth()->getJson('/api/attendance/today');

        $response->assertOk();
        $schedules = $response->json('shift_schedules');
        $this->assertCount(1, $schedules);
        $this->assertTrue($schedules[0]['is_flexible']);
        $this->assertSame('09:00', substr($schedules[0]['shift']['start_time'], 0, 5));
        $this->assertSame('18:00', substr($schedules[0]['shift']['end_time'], 0, 5));
    }

    public function test_checkin_via_api_token_succeeds_within_gps_radius(): void
    {
        $response = $this->auth()->postJson('/api/attendance/check-in', [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('attendance_logs', ['employee_id' => $this->employee->id]);
    }

    public function test_checkin_via_api_blocked_without_office_wifi(): void
    {
        $response = $this->auth()
            ->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->postJson('/api/attendance/check-in', ['lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_history_endpoint_returns_own_logs_only(): void
    {
        $this->auth()->postJson('/api/attendance/check-in', [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ])->assertOk();

        $response = $this->auth()->getJson('/api/attendance/history');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_token_without_permission_is_forbidden(): void
    {
        $noPermUser = User::factory()->create(['status' => 'active']);
        $token = $noPermUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/attendance/check-in', ['lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG]);

        $response->assertStatus(403);
    }
}
