<?php

namespace Tests\Feature;

use App\Models\AttendanceAlert;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftCoverageRequirement;
use App\Models\ShiftSchedule;
use App\Models\Team;
use App\Models\User;
use App\Services\AttendanceAlertService;
use App\Services\ShiftCoverageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftCoverageAndAttendanceAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Branch $branch;
    protected Team $team;
    protected Shift $morningShift;
    protected Shift $eveningShift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(
            ['name' => 'CN Test Định Biên'],
            ['code' => 'CN_TEST_DB', 'is_active' => true]
        );

        $this->team = Team::firstOrCreate(
            ['branch_id' => $this->branch->id, 'name' => 'Bếp Test'],
            ['code' => 'BEP_TEST', 'is_active' => true]
        );

        $this->morningShift = Shift::firstOrCreate(
            ['code' => 'CA_SANG_TEST'],
            [
                'name'                => 'Ca Sáng Test',
                'branch_id'           => $this->branch->id,
                'start_time'          => '11:00',
                'end_time'            => '15:00',
                'is_overnight'        => false,
                'work_mode'           => 'onsite',
                'is_active'           => true,
                'grace_late_minutes'  => 10,
                'grace_early_minutes' => 10,
            ]
        );

        $this->eveningShift = Shift::firstOrCreate(
            ['code' => 'CA_TOI_TEST'],
            [
                'name'                => 'Ca Tối Test',
                'branch_id'           => $this->branch->id,
                'start_time'          => '18:00',
                'end_time'            => '00:00',
                'is_overnight'        => true,
                'work_mode'           => 'onsite',
                'is_active'           => true,
                'grace_late_minutes'  => 10,
                'grace_early_minutes' => 10,
            ]
        );

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $perms = [
            'view-shift-coverage',
            'manage-shift-coverage',
            'view-attendance-alerts',
            'manage-attendance-alerts',
            'view-shift-schedules',
            'create-shift-schedules',
            'edit-shift-schedules',
            'delete-shift-schedules',
            'view-attendance-logs',
            'manage-attendance-logs',
        ];
        foreach ($perms as $p) {
            $perm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($perm);
        }

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_test_coverage@tonhr.com'],
            ['name' => 'Admin Test Coverage', 'password' => bcrypt('password'), 'status' => 'active']
        );
        $this->adminUser->assignRole($role);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_can_create_shift_coverage_requirement()
    {
        $response = $this->actingAs($this->adminUser)->post(route('shift-coverage-requirements.store'), [
            'branch_id'       => $this->branch->id,
            'team_id'         => $this->team->id,
            'name'            => 'Định biên trưa Bếp',
            'days_of_week'    => [1, 2, 3, 4, 5],
            'start_time'      => '11:00',
            'end_time'        => '15:00',
            'minimum_staff'   => 2,
            'target_staff'    => 3,
            'effective_from'  => '2026-01-01',
            'effective_until' => '2026-12-31',
            'is_active'       => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_coverage_requirements', [
            'name'          => 'Định biên trưa Bếp',
            'minimum_staff' => 2,
            'target_staff'  => 3,
        ]);
    }

    public function test_cannot_create_overlapping_shift_coverage_requirement()
    {
        ShiftCoverageRequirement::create([
            'branch_id'       => $this->branch->id,
            'team_id'         => $this->team->id,
            'name'            => 'Khung 1',
            'days_of_week'    => [1, 2, 3],
            'start_time'      => '11:00',
            'end_time'        => '15:00',
            'minimum_staff'   => 2,
            'effective_from'  => '2026-01-01',
            'effective_until' => '2026-12-31',
            'is_active'       => true,
        ]);

        // Gửi khung trùng thứ và giao khung giờ (13:00 - 17:00)
        $response = $this->actingAs($this->adminUser)->post(route('shift-coverage-requirements.store'), [
            'branch_id'       => $this->branch->id,
            'team_id'         => $this->team->id,
            'name'            => 'Khung Trùng',
            'days_of_week'    => [3, 4, 5], // Thứ 4 (day 3) giao nhau
            'start_time'      => '13:00',
            'end_time'        => '17:00',
            'minimum_staff'   => 2,
            'effective_from'  => '2026-01-01',
            'effective_until' => '2026-12-31',
        ]);

        $response->assertSessionHasErrors('overlap');
    }

    public function test_shift_coverage_calculation_detects_shortage_correctly()
    {
        $req = ShiftCoverageRequirement::create([
            'branch_id'       => $this->branch->id,
            'team_id'         => $this->team->id,
            'name'            => 'Ca Sáng Test Coverage',
            'days_of_week'    => [1, 2, 3, 4, 5, 6, 7],
            'start_time'      => '11:00',
            'end_time'        => '15:00',
            'minimum_staff'   => 3,
            'target_staff'    => 4,
            'effective_from'  => '2026-01-01',
            'is_active'       => true,
        ]);

        $emp1 = Employee::create([
            'code' => 'EMP_COV_1', 'name' => 'NV 1', 'branch_id' => $this->branch->id, 'team_id' => $this->team->id, 'is_active' => true,
        ]);
        $emp2 = Employee::create([
            'code' => 'EMP_COV_2', 'name' => 'NV 2', 'branch_id' => $this->branch->id, 'team_id' => $this->team->id, 'is_active' => true,
        ]);

        $today = Carbon::parse('2026-10-05'); // Thứ 2

        // Xếp 2 người cho ca 11:00 - 15:00
        $sch1 = ShiftSchedule::create([
            'employee_id' => $emp1->id, 'branch_id' => $this->branch->id, 'team_id' => $this->team->id,
            'shift_id' => $this->morningShift->id, 'work_date' => $today->toDateString(), 'status' => 'scheduled',
        ]);
        $sch2 = ShiftSchedule::create([
            'employee_id' => $emp2->id, 'branch_id' => $this->branch->id, 'team_id' => $this->team->id,
            'shift_id' => $this->morningShift->id, 'work_date' => $today->toDateString(), 'status' => 'scheduled',
        ]);

        $coverageService = app(ShiftCoverageService::class);
        $analysis = $coverageService->analyzeFrameCoverage($req, collect([$sch1, $sch2]), $today);

        // Có 2 người làm nhưng tối thiểu cần 3 -> Trạng thái 'shortage', thiếu 1 người
        $this->assertEquals('shortage', $analysis['status']);
        $this->assertEquals(2, $analysis['scheduled_coverage']);
        $this->assertEquals('Thiếu 1 người', $analysis['status_label']);
    }

    public function test_frame_lists_only_employees_whose_shift_overlaps_the_frame()
    {
        $noonReq = ShiftCoverageRequirement::create([
            'branch_id' => $this->branch->id, 'team_id' => $this->team->id, 'name' => 'Trưa',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'start_time' => '11:00', 'end_time' => '15:00',
            'minimum_staff' => 1, 'target_staff' => 1, 'effective_from' => '2026-01-01', 'is_active' => true,
        ]);
        $nightReq = ShiftCoverageRequirement::create([
            'branch_id' => $this->branch->id, 'team_id' => $this->team->id, 'name' => 'Tối',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'start_time' => '18:00', 'end_time' => '00:00',
            'minimum_staff' => 1, 'target_staff' => 1, 'effective_from' => '2026-01-01', 'is_active' => true,
        ]);

        $morningEmp = Employee::create(['code' => 'EMP_FR_1', 'name' => 'NV Sáng', 'branch_id' => $this->branch->id, 'team_id' => $this->team->id, 'is_active' => true]);
        $eveningEmp = Employee::create(['code' => 'EMP_FR_2', 'name' => 'NV Tối', 'branch_id' => $this->branch->id, 'team_id' => $this->team->id, 'is_active' => true]);

        $monday = Carbon::parse('2026-10-05');
        ShiftSchedule::create([
            'employee_id' => $morningEmp->id, 'branch_id' => $this->branch->id, 'team_id' => $this->team->id,
            'shift_id' => $this->morningShift->id, 'work_date' => $monday->toDateString(), 'status' => 'scheduled',
        ]);
        ShiftSchedule::create([
            'employee_id' => $eveningEmp->id, 'branch_id' => $this->branch->id, 'team_id' => $this->team->id,
            'shift_id' => $this->eveningShift->id, 'work_date' => $monday->toDateString(), 'status' => 'scheduled',
        ]);

        $data = app(ShiftCoverageService::class)->getWeeklyCoverage($this->branch->id, $this->team->id, $monday);
        $teamResult = collect($data['daily_coverage'][$monday->toDateString()]['team_results'])
            ->firstWhere(fn($tr) => $tr['team']->id === $this->team->id);
        $namesFor = fn($req) => collect($teamResult['frames'])
            ->firstWhere(fn($fr) => $fr['requirement']->id === $req->id)['scheduled_employees']
            ->pluck('employee.name')->all();

        $this->assertSame(['NV Sáng'], $namesFor($noonReq));
        $this->assertSame(['NV Tối'], $namesFor($nightReq));
        // Danh sách theo cả bộ phận trong ngày (dùng cho chế độ "Theo ngày") vẫn đủ 2 người
        $this->assertCount(2, $teamResult['scheduled_employees']);
    }

    public function test_single_employee_with_two_overlapping_shifts_counts_as_one()
    {
        $req = ShiftCoverageRequirement::create([
            'branch_id'       => $this->branch->id,
            'team_id'         => $this->team->id,
            'name'            => 'Khung Trưa',
            'days_of_week'    => [1, 2, 3, 4, 5, 6, 7],
            'start_time'      => '11:00',
            'end_time'        => '15:00',
            'minimum_staff'   => 2,
            'effective_from'  => '2026-01-01',
            'is_active'       => true,
        ]);

        $emp = Employee::create([
            'code' => 'EMP_MULTI_SHIFT', 'name' => 'NV Đa Ca', 'branch_id' => $this->branch->id, 'team_id' => $this->team->id, 'is_active' => true,
        ]);

        $today = Carbon::parse('2026-10-05');

        // Cùng 1 nhân viên nhưng có 2 ca chồng nhau trong cùng khung giờ
        $sch1 = ShiftSchedule::create([
            'employee_id' => $emp->id, 'branch_id' => $this->branch->id, 'team_id' => $this->team->id,
            'work_date' => $today->toDateString(), 'status' => 'scheduled',
            'custom_start_time' => '11:00', 'custom_end_time' => '14:00',
        ]);
        $sch2 = ShiftSchedule::create([
            'employee_id' => $emp->id, 'branch_id' => $this->branch->id, 'team_id' => $this->team->id,
            'work_date' => $today->toDateString(), 'status' => 'scheduled',
            'custom_start_time' => '13:00', 'custom_end_time' => '15:00',
        ]);

        $coverageService = app(ShiftCoverageService::class);
        $analysis = $coverageService->analyzeFrameCoverage($req, collect([$sch1, $sch2]), $today);

        // Quy tắc nghiệp vụ 4.5: Nhân viên chỉ được tính MỘT NGƯỜI trong cùng một thời điểm!
        $this->assertEquals(1, $analysis['scheduled_coverage']);
        $this->assertEquals('shortage', $analysis['status']);
    }

    public function test_scan_alerts_generates_missing_check_in_alert()
    {
        $emp = Employee::create([
            'code' => 'EMP_MISSED_CI', 'name' => 'NV Quên Check In', 'branch_id' => $this->branch->id, 'team_id' => $this->team->id, 'is_active' => true,
        ]);

        $yesterday = now()->subDays(2)->toDateString();

        $schedule = ShiftSchedule::create([
            'employee_id' => $emp->id,
            'branch_id'   => $this->branch->id,
            'team_id'     => $this->team->id,
            'shift_id'    => $this->morningShift->id,
            'work_date'   => $yesterday,
            'status'      => 'scheduled',
        ]);

        $alertService = app(AttendanceAlertService::class);
        $result = $alertService->scanAlerts($emp->id);

        $this->assertEquals(1, $result['missing_check_in_created']);

        $alert = AttendanceAlert::where('shift_schedule_id', $schedule->id)
            ->where('alert_type', 'missing_check_in')
            ->first();

        $this->assertNotNull($alert);
        $this->assertEquals('open', $alert->status);
    }

    public function test_employee_popup_shows_once_then_dismiss_marks_seen_for_admin()
    {
        $user = User::factory()->create(['status' => 'active']);
        $emp = Employee::create([
            'code' => 'EMP_POPUP', 'name' => 'NV Popup', 'branch_id' => $this->branch->id,
            'team_id' => $this->team->id, 'user_id' => $user->id, 'is_active' => true,
        ]);
        ShiftSchedule::create([
            'employee_id' => $emp->id, 'branch_id' => $this->branch->id, 'team_id' => $this->team->id,
            'shift_id' => $this->morningShift->id, 'work_date' => now()->subDays(2)->toDateString(), 'status' => 'scheduled',
        ]);

        // Lần đầu truy cập: popup hiện (cảnh báo chưa xem)
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertSee('attendanceAlertDetailsModal');

        $alert = AttendanceAlert::where('employee_id', $emp->id)->firstOrFail();
        $this->assertEquals('open', $alert->status);
        $this->assertNull($alert->seen_at);

        // Nhân viên bấm tắt -> seen + seen_at
        $this->actingAs($user)->postJson(route('attendance-alerts.dismiss-all'))->assertOk();
        $alert->refresh();
        $this->assertEquals('seen', $alert->status);
        $this->assertNotNull($alert->seen_at);

        // Truy cập lại: không hiện popup nữa
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertDontSee('attendanceAlertDetailsModal');

        // Admin thấy "đã xem lúc ..."
        $this->actingAs($this->adminUser)->get(route('attendance-alerts.index'))
            ->assertOk()->assertSee('NV đã xem lúc');
    }

    public function test_alternative_shift_group_check_in_suppresses_alert_for_group()
    {
        $emp = Employee::create([
            'code' => 'EMP_ALT_SHIFT', 'name' => 'NV Ca Thay Thế', 'branch_id' => $this->branch->id, 'team_id' => $this->team->id, 'is_active' => true,
        ]);

        $yesterday = now()->subDays(1)->toDateString();
        $groupId = 'alt_group_123';

        // Tạo 2 ca là phương án thay thế của nhau
        $sch1 = ShiftSchedule::create([
            'employee_id'          => $emp->id,
            'branch_id'            => $this->branch->id,
            'shift_id'             => $this->morningShift->id,
            'work_date'            => $yesterday,
            'status'               => 'scheduled',
            'alternative_group_id' => $groupId,
        ]);

        $sch2 = ShiftSchedule::create([
            'employee_id'          => $emp->id,
            'branch_id'            => $this->branch->id,
            'shift_id'             => $this->eveningShift->id,
            'work_date'            => $yesterday,
            'status'               => 'scheduled',
            'alternative_group_id' => $groupId,
        ]);

        // Nhân viên đã check-in ca 1
        AttendanceLog::create([
            'employee_id'       => $emp->id,
            'shift_schedule_id' => $sch1->id,
            'work_date'         => $yesterday,
            'check_in_at'       => Carbon::parse("{$yesterday} 11:05"),
        ]);

        $alertService = app(AttendanceAlertService::class);
        $result = $alertService->scanAlerts($emp->id);

        // Vì đã check-in 1 lựa chọn trong nhóm thay thế, KHÔNG sinh cảnh báo cho ca còn lại!
        $this->assertEquals(0, $result['missing_check_in_created']);
        $this->assertDatabaseMissing('attendance_alerts', [
            'shift_schedule_id' => $sch2->id,
            'alert_type'        => 'missing_check_in',
        ]);
    }

    public function test_admin_can_excuse_alert_with_note()
    {
        $emp = Employee::create([
            'code' => 'EMP_EXCUSE_TEST', 'name' => 'NV Miễn Cảnh Báo', 'branch_id' => $this->branch->id, 'team_id' => $this->team->id, 'is_active' => true,
        ]);

        $sch = ShiftSchedule::create([
            'employee_id' => $emp->id,
            'branch_id'   => $this->branch->id,
            'shift_id'    => $this->morningShift->id,
            'work_date'   => now()->subDays(2)->toDateString(),
            'status'      => 'scheduled',
        ]);

        $alert = AttendanceAlert::create([
            'employee_id'       => $emp->id,
            'shift_schedule_id' => $sch->id,
            'alert_type'        => 'missing_check_in',
            'status'            => 'open',
            'triggered_at'      => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('attendance-alerts.excuse', $alert), [
            'resolution_note' => 'Nhân viên đi công tác gặp khách hàng ngoài chi nhánh',
        ]);

        $response->assertRedirect();
        $alert->refresh();
        $this->assertEquals('excused', $alert->status);
        $this->assertEquals('Nhân viên đi công tác gặp khách hàng ngoài chi nhánh', $alert->resolution_note);
        $this->assertEquals($this->adminUser->id, $alert->resolved_by);
    }

    public function test_operational_schedule_page_loads_successfully()
    {
        // 1. Mặc định dạng bảng tuần (Table View)
        $response = $this->actingAs($this->adminUser)->get(route('operational-schedule.index', [
            'branch_id' => $this->branch->id,
            'view'      => 'table',
        ]));

        $response->assertOk();
        $response->assertSee('Lịch vận hành & Định biên tuần');
        $response->assertSee('Ma trận định biên 7 ngày');
        $response->assertSee('Bảng tuần');
        $response->assertSee('Danh sách');

        // 2. Chế độ xem dạng danh sách (List View)
        $responseList = $this->actingAs($this->adminUser)->get(route('operational-schedule.index', [
            'branch_id' => $this->branch->id,
            'view'      => 'list',
        ]));
        $responseList->assertOk();
        $responseList->assertSee('Danh sách tất cả khung vận hành trong tuần');
        $responseList->assertSee('Chỉ hiện khung thiếu người');

        // 3. Lọc theo bộ phận
        $responseTeam = $this->actingAs($this->adminUser)->get(route('operational-schedule.index', [
            'branch_id' => $this->branch->id,
            'team_id'   => $this->team->id,
            'view'      => 'table',
        ]));
        $responseTeam->assertOk();
        $responseTeam->assertSee($this->team->name);
    }
}
