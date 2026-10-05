<?php

namespace Tests\Feature;

use App\Models\AttendanceLocation;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Xác nhận việc duyệt đơn nghỉ theo giờ thực sự thay đổi hành vi chấm công: bị chặn/tính trễ
 * theo khung giờ CÒN LẠI sau khi trừ phần nghỉ (ShiftSchedule::adjusted_start_time), không phải
 * theo giờ ca gốc.
 */
class PartialLeaveAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICE_LAT = 16.0544;
    private const OFFICE_LNG = 108.2022;

    private User $user;
    private Employee $employee;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::today()->setTime(8, 0));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => 'staff']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'checkin-attendance']));

        $this->user = User::factory()->create();
        $this->user->assignRole('staff');

        $this->branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        $this->employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'user_id' => $this->user->id,
            'branch_id' => $this->branch->id, 'is_active' => true,
        ]);

        AttendanceLocation::create([
            'branch_id'     => $this->branch->id,
            'name'          => 'Văn phòng chính',
            'latitude'      => self::OFFICE_LAT,
            'longitude'     => self::OFFICE_LNG,
            'radius_meters' => 100,
            'allowed_ips'   => ['127.0.0.1'],
            'is_active'     => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Ca 9h-18h, nghỉ trưa 60p, đã được duyệt nghỉ sáng (9h-12h) -> chỉ còn phải chấm công
     * từ 12h đến 18h (adjusted_start_time = 12:00).
     */
    private function approvedMorningOffSchedule(): ShiftSchedule
    {
        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '09:00', 'end_time' => '18:00',
            'break_minutes' => 60, 'work_mode' => 'onsite', 'grace_late_minutes' => 10,
        ]);

        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'branch_id' => $this->branch->id,
            'work_date' => today()->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $leave = LeaveRequest::create([
            'code' => 'LR-ATT-01', 'employee_id' => $this->employee->id,
            'date_from' => today()->toDateString(), 'date_to' => today()->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ sáng', 'status' => 'pending',
            'is_partial_day' => true, 'from_time' => '09:00', 'to_time' => '12:00',
            'day_fraction' => 0.33, 'shift_schedule_id' => $schedule->id,
        ]);
        // Duyệt thủ công (không qua HTTP) — chỉ cần đúng hiệu ứng adjusted_start_time.
        $leave->update(['status' => 'approved']);
        $schedule->update(['adjusted_start_time' => '12:00']);

        return $schedule->fresh();
    }

    public function test_checkin_before_adjusted_start_succeeds_without_late_penalty(): void
    {
        // Không còn chặn check-in ngoài khung giờ ca (đã bỏ early_checkin_minutes) — check-in
        // sớm hơn giờ bắt đầu ĐÃ ĐIỀU CHỈNH (12h) vẫn thành công và không bị tính trễ.
        $this->approvedMorningOffSchedule();

        // 10h — sau giờ bắt đầu CA GỐC (9h) nhưng vẫn trước giờ bắt đầu ĐÃ ĐIỀU CHỈNH (12h).
        Carbon::setTestNow(today()->setTime(10, 0));

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id'  => $this->employee->id,
            'late_minutes' => 0,
        ]);
    }

    public function test_checkin_after_adjusted_start_succeeds_without_late_penalty(): void
    {
        $this->approvedMorningOffSchedule();

        // 12h05 — trong khung giờ còn lại phải chấm công (từ 12h, theo giờ đã điều chỉnh),
        // trong phạm vi grace mặc định.
        Carbon::setTestNow(today()->setTime(12, 5));

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id'       => $this->employee->id,
            'shift_start_time'  => '12:00',
            'shift_break_minutes' => 0,
            'late_minutes'      => 0,
        ]);
    }

    public function test_checkin_far_after_adjusted_start_is_flagged_late(): void
    {
        $this->approvedMorningOffSchedule();

        // 12h30 — trễ 30 phút so với giờ bắt đầu ĐÃ ĐIỀU CHỈNH (12h, trừ 10p grace = 20p trễ),
        // không phải so với 9h gốc (nếu tính theo giờ gốc thì đã trễ tận 3h+).
        Carbon::setTestNow(today()->setTime(12, 30));

        $response = $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id'  => $this->employee->id,
            'late_minutes' => 20,
        ]);
    }
}
