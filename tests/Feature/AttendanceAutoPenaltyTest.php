<?php

namespace Tests\Feature;

use App\Models\AttendanceImportRule;
use App\Models\AttendanceLocation;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Penalty;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceAutoPenaltyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;
    private Branch $branch;

    private const OFFICE_LAT = 16.0544;
    private const OFFICE_LNG = 108.2022;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'staff']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'checkin-attendance']));

        // notifyPenaltyCreated() tra cứu User::permission('approve-penalties') — Spatie yêu cầu
        // permission này tồn tại trước, dù không có ai được gán quyền trong test này.
        Permission::firstOrCreate(['name' => 'approve-penalties']);

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
            'allowed_ips'   => ['127.0.0.1'],
            'is_active'     => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeRule(string $type, int $min, ?int $max, int $points): AttendanceImportRule
    {
        $violation = Violation::create([
            'name'            => $type === 'late' ? "Đi trễ {$min}-{$max} phút" : "Về sớm {$min}-{$max} phút",
            'penalty_type'    => 'points',
            'points_deducted' => $points,
            'is_active'       => true,
        ]);

        return AttendanceImportRule::create([
            'type'         => $type,
            'min_minutes'  => $min,
            'max_minutes'  => $max,
            'violation_id' => $violation->id,
            'label'        => "Rule {$type} {$min}-{$max}",
            'is_active'    => true,
        ]);
    }

    private function makeOnsiteShiftToday(int $graceLate = 0, int $graceEarly = 0): Shift
    {
        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính',
            'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
            'grace_late_minutes' => $graceLate, 'grace_early_minutes' => $graceEarly,
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

    // ── Happy path ───────────────────────────────────────────────────────

    public function test_late_checkin_beyond_rule_threshold_creates_pending_penalty(): void
    {
        $rule = $this->makeRule('late', 15, 30, 10);
        $this->makeOnsiteShiftToday();

        // Ca 08h-17h, check-in lúc 08h20 -> trễ 20 phút, khớp rule 15-30 phút.
        Carbon::setTestNow(Carbon::today()->setTime(8, 20));

        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ])->assertStatus(200)->assertJson(['success' => true]);

        $log = AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertEquals(20, $log->late_minutes);
        $this->assertNotNull($log->late_penalty_id);

        $penalty = Penalty::find($log->late_penalty_id);
        $this->assertNotNull($penalty);
        $this->assertEquals('pending', $penalty->status);
        $this->assertEquals($this->employee->id, $penalty->employee_id);
        $this->assertEquals($rule->violation_id, $penalty->violation_id);
        $this->assertEquals(10, $penalty->total_points_deducted);
    }

    public function test_early_checkout_beyond_rule_threshold_creates_pending_penalty(): void
    {
        $rule = $this->makeRule('early', 10, null, 5);
        $this->makeOnsiteShiftToday();

        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ])->assertStatus(200);

        // Ca kết thúc 17h, check-out lúc 16h30 -> về sớm 30 phút, khớp rule "từ 10 phút trở lên".
        Carbon::setTestNow(Carbon::today()->setTime(16, 30));

        $this->actingAs($this->user)->postJson(route('attendance.check-out'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ])->assertStatus(200)->assertJson(['success' => true]);

        $log = AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertEquals(30, $log->early_minutes);
        $this->assertNotNull($log->early_penalty_id);

        $penalty = Penalty::find($log->early_penalty_id);
        $this->assertEquals('pending', $penalty->status);
        $this->assertEquals($rule->violation_id, $penalty->violation_id);
        $this->assertEquals(5, $penalty->total_points_deducted);
    }

    // ── Không khớp ngưỡng nào ───────────────────────────────────────────

    public function test_late_checkin_without_matching_rule_does_not_create_penalty(): void
    {
        // Không cấu hình rule nào.
        $this->makeOnsiteShiftToday();

        Carbon::setTestNow(Carbon::today()->setTime(8, 20));

        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ])->assertStatus(200);

        $log = AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertEquals(20, $log->late_minutes);
        $this->assertNull($log->late_penalty_id);
        $this->assertEquals(0, Penalty::count());
    }

    public function test_late_minutes_below_lowest_rule_threshold_does_not_create_penalty(): void
    {
        $this->makeRule('late', 15, 30, 10);
        $this->makeOnsiteShiftToday();

        // Trễ 5 phút -> thấp hơn ngưỡng min_minutes=15 của rule duy nhất.
        Carbon::setTestNow(Carbon::today()->setTime(8, 5));

        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ])->assertStatus(200);

        $log = AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertEquals(5, $log->late_minutes);
        $this->assertNull($log->late_penalty_id);
        $this->assertEquals(0, Penalty::count());
    }

    public function test_late_minutes_within_grace_period_does_not_create_penalty(): void
    {
        $this->makeRule('late', 1, null, 10);
        $this->makeOnsiteShiftToday(graceLate: 15);

        // Đến trễ thực tế 10 phút nhưng ân hạn 15 phút -> late_minutes tính ra = 0.
        Carbon::setTestNow(Carbon::today()->setTime(8, 10));

        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ])->assertStatus(200);

        $log = AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertEquals(0, $log->late_minutes);
        $this->assertNull($log->late_penalty_id);
        $this->assertEquals(0, Penalty::count());
    }

    // ── Idempotency ──────────────────────────────────────────────────────

    public function test_service_does_not_duplicate_penalty_when_log_already_has_one(): void
    {
        $this->makeRule('late', 15, 30, 10);
        $this->makeOnsiteShiftToday();

        Carbon::setTestNow(Carbon::today()->setTime(8, 20));
        $this->actingAs($this->user)->postJson(route('attendance.check-in'), [
            'lat' => self::OFFICE_LAT, 'lng' => self::OFFICE_LNG,
        ])->assertStatus(200);

        $log = AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertEquals(1, Penalty::count());

        // Gọi lại service trực tiếp trên cùng bản ghi (mô phỏng việc tính lại) -> không tạo thêm.
        $this->actingAs($this->user);
        app(\App\Services\AttendanceAutoPenaltyService::class)->createIfNeeded($log, 'late', 20);

        $this->assertEquals(1, Penalty::count());
    }

    // ── WFH bỏ qua xác thực vị trí nhưng VẪN tính trễ/sớm ────────────────

    public function test_wfh_checkin_still_computes_late_minutes_and_triggers_penalty(): void
    {
        $this->makeRule('late', 1, null, 10);

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

        Carbon::setTestNow(Carbon::today()->setTime(10, 0));

        $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->postJson(route('attendance.check-in'), ['lat' => 0, 'lng' => 0])
            ->assertStatus(200)->assertJson(['success' => true]);

        $log = AttendanceLog::where('employee_id', $this->employee->id)->first();
        $this->assertEquals(120, $log->late_minutes);
        $this->assertNotNull($log->late_penalty_id);
        $this->assertEquals(1, Penalty::count());
    }
}
