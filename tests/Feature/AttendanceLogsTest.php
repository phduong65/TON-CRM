<?php

namespace Tests\Feature;

use App\Exports\AttendanceLogsExport;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceLogsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Employee $employeeA;
    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'manager']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-attendance']));

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->employeeA = Employee::create(['code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'branch_id' => $branch->id, 'is_active' => true]);
        $this->employeeB = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'branch_id' => $branch->id, 'is_active' => true]);

        AttendanceLog::create(['employee_id' => $this->employeeA->id, 'work_date' => now()->toDateString(), 'check_in_at' => now()]);
        AttendanceLog::create(['employee_id' => $this->employeeB->id, 'work_date' => now()->toDateString(), 'check_in_at' => now()]);
    }

    public function test_manager_can_view_attendance_logs_with_employee_combobox(): void
    {
        $response = $this->actingAs($this->manager)->get(route('attendance-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('Nguyễn Văn A');
        $response->assertSee('Trần Thị B');
        $response->assertSee('data-employee-combobox', false);
    }

    public function test_filtering_by_employee_id_narrows_results(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('attendance-logs.index', ['employee_id' => $this->employeeA->id]));

        $response->assertStatus(200);
        $response->assertSee('Nguyễn Văn A');
        $response->assertDontSee('Trần Thị B');
    }

    public function test_overtime_hours_shown_as_badge_when_present(): void
    {
        AttendanceLog::create([
            'employee_id' => $this->employeeA->id, 'work_date' => now()->addDay()->toDateString(),
            'check_in_at' => now(), 'check_out_at' => now()->addHours(9), 'overtime_hours' => 2.5,
        ]);

        // Trang mặc định chỉ hiển thị hôm nay khi chưa lọc gì — dùng ?all=1 để thấy bản ghi ngày mai.
        $response = $this->actingAs($this->manager)->get(route('attendance-logs.index', ['all' => 1]));

        $response->assertStatus(200);
        $response->assertSee('+2.5h');
    }

    public function test_approved_partial_day_leave_reduces_cong_instead_of_full_credit(): void
    {
        // Tái hiện đúng bug thực tế: ca full-time 09:00-18:00, nhân viên xin nghỉ nửa ngày
        // 13:00-18:00 (đã duyệt), đi làm phần còn lại 09:07-13:00 — "Công" phải trừ đúng phần đã
        // nghỉ (1 - 0.56 = 0.44), KHÔNG được tính đủ 1 công như nhánh full-time thường (xem
        // AttendanceLog::computeCong()).
        $workDate = now()->toDateString();
        $shift = Shift::create([
            'code' => 'CA-VP-TEST', 'name' => 'Ca văn phòng', 'start_time' => '09:00', 'end_time' => '18:00',
            'break_minutes' => 60, 'work_mode' => 'onsite', 'shift_type' => 'fulltime',
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employeeA->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        \App\Models\LeaveRequest::create([
            'code' => 'LR-TEST-0001', 'employee_id' => $this->employeeA->id,
            'date_from' => $workDate, 'date_to' => $workDate, 'shift_schedule_id' => $schedule->id,
            'is_partial_day' => true, 'from_time' => '13:00', 'to_time' => '18:00', 'day_fraction' => 0.56,
            'type' => 'annual', 'reason' => 'Đi khám bệnh', 'status' => 'approved',
        ]);

        // employeeA đã có 1 log rỗng từ setUp() cho work_date hôm nay — cập nhật lại đúng theo kịch bản.
        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->where('work_date', $workDate)->first();
        $log->update([
            'shift_schedule_id' => $schedule->id,
            'check_in_at'       => $workDate . ' 09:07:00',
            'check_out_at'      => $workDate . ' 13:00:00',
        ]);

        $this->assertEquals(0.44, $log->computeCong(null, 0.56));

        $response = $this->actingAs($this->manager)->get(route('attendance-logs.index', ['all' => 1]));

        $response->assertStatus(200);
        $response->assertSee('0.44 công');
        $response->assertDontSee('1 công');
    }

    public function test_partial_day_leave_on_one_shift_does_not_reduce_cong_of_other_shift_same_day(): void
    {
        // Bug thực tế: nhân viên xếp đa ca trong ngày (ca sáng + ca tối), xin nghỉ theo giờ CHỈ cho
        // ca sáng (đã duyệt). Ca tối đi làm đầy đủ, chấm công đủ vào/ra — "Công" của ca tối PHẢI
        // tính bình thường (không bị đơn nghỉ của ca sáng đè lên chỉ vì index cũ chỉ khoá theo
        // employeeId_date, không phân biệt shift_schedule_id).
        $workDate = now()->toDateString();

        $morningShift = Shift::create([
            'code' => 'CA-SANG-TEST', 'name' => 'Ca sáng', 'start_time' => '11:00', 'end_time' => '15:00',
            'work_mode' => 'onsite', 'shift_type' => 'parttime',
        ]);
        $eveningShift = Shift::create([
            'code' => 'CA-TOI-TEST', 'name' => 'Ca tối', 'start_time' => '18:00', 'end_time' => '00:00',
            'work_mode' => 'onsite', 'shift_type' => 'parttime', 'is_overnight' => true,
        ]);

        $morningSchedule = ShiftSchedule::create([
            'employee_id' => $this->employeeA->id, 'shift_id' => $morningShift->id,
            'work_date' => $workDate, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);
        $eveningSchedule = ShiftSchedule::create([
            'employee_id' => $this->employeeA->id, 'shift_id' => $eveningShift->id,
            'work_date' => $workDate, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        \App\Models\LeaveRequest::create([
            'code' => 'LR-TEST-0002', 'employee_id' => $this->employeeA->id,
            'date_from' => $workDate, 'date_to' => $workDate, 'shift_schedule_id' => $morningSchedule->id,
            'is_partial_day' => true, 'from_time' => '11:00', 'to_time' => '15:00', 'day_fraction' => 1.0,
            'type' => 'annual', 'reason' => 'Nghỉ ca sáng', 'status' => 'approved',
        ]);

        // employeeA đã có 1 log rỗng từ setUp() cho work_date hôm nay — dùng lại cho ca tối.
        $eveningLog = AttendanceLog::where('employee_id', $this->employeeA->id)->where('work_date', $workDate)->first();
        $eveningLog->update([
            'shift_schedule_id' => $eveningSchedule->id,
            'check_in_at'       => $workDate . ' 17:41:00',
            'check_out_at'      => now()->addDay()->toDateString() . ' 00:09:00',
        ]);

        $response = $this->actingAs($this->manager)->get(route('attendance-logs.index', ['all' => 1]));

        $response->assertStatus(200);
        // Ca tối đi làm đủ 6h thực tế / 8h công chuẩn (parttime, không set standard_work_hours
        // riêng nên mặc định 8h — xem Shift::standardWorkHours()) => 0.75 công, KHÔNG bị trừ về 0
        // vì đơn nghỉ của ca sáng.
        $response->assertSee('0.75 công');
        $response->assertDontSee('0 công');
    }

    public function test_full_credit_log_shows_da_tha_loi_badge(): void
    {
        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->where('work_date', now()->toDateString())->first();
        $log->update(['full_credit' => true, 'late_minutes' => 0]);

        $response = $this->actingAs($this->manager)->get(route('attendance-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('Đã tha lỗi');
    }

    public function test_approved_time_change_without_full_credit_shows_ghi_nhan_badge(): void
    {
        $workDate = now()->toDateString();
        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->where('work_date', $workDate)->first();
        $log->update(['late_minutes' => 30, 'full_credit' => false]);

        \App\Models\StaffRequest::create([
            'code' => 'TC-BADGE-0001', 'employee_id' => $this->employeeA->id,
            'type' => 'time_change', 'work_date' => $workDate,
            'payload' => ['new_check_in' => '08:30', 'new_check_out' => '17:00'],
            'reason' => 'Xác nhận giờ vào thực tế', 'status' => 'approved',
        ]);

        $response = $this->actingAs($this->manager)->get(route('attendance-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('Trễ 30p');
        $response->assertSee('Ghi nhận');
        $response->assertDontSee('Đã tha lỗi');
    }

    public function test_index_without_filters_defaults_to_showing_only_todays_logs(): void
    {
        AttendanceLog::create([
            'employee_id' => $this->employeeA->id, 'work_date' => now()->subDay()->toDateString(),
            'check_in_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->manager)->get(route('attendance-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('Hôm nay');
        $response->assertSee(now()->format('d/m/Y'));
        $response->assertDontSee(now()->subDay()->format('d/m/Y'));
    }

    public function test_view_all_query_param_bypasses_default_today_filter(): void
    {
        AttendanceLog::create([
            'employee_id' => $this->employeeA->id, 'work_date' => now()->subDay()->toDateString(),
            'check_in_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->manager)->get(route('attendance-logs.index', ['all' => 1]));

        $response->assertStatus(200);
        $response->assertDontSee('Hôm nay</span>', false);
        $response->assertSee(now()->format('d/m/Y'));
        $response->assertSee(now()->subDay()->format('d/m/Y'));
    }

    public function test_filtering_by_date_range_bypasses_default_today_filter(): void
    {
        AttendanceLog::create([
            'employee_id' => $this->employeeA->id, 'work_date' => now()->subDay()->toDateString(),
            'check_in_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->manager)->get(route('attendance-logs.index', [
            'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->subDay()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee(now()->subDay()->format('d/m/Y'));
        $response->assertDontSee(now()->format('d/m/Y'));
    }

    public function test_user_without_permission_cannot_view_attendance_logs(): void
    {
        $noPermUser = User::factory()->create();
        $response = $this->actingAs($noPermUser)->get(route('attendance-logs.index'));
        $response->assertStatus(403);
    }

    // ── Xuất Excel ───────────────────────────────────────────────────────

    public function test_manager_can_export_attendance_logs(): void
    {
        $role = Role::where('name', 'manager')->first();
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'export-attendance']));

        $response = $this->actingAs($this->manager)->get(route('attendance-logs.export', [
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** Lấy danh sách employee_id có trong file xuất (Excel::fake + matchByRegex vì tên file có timestamp). */
    private function exportedEmployeeIds(array $query): array
    {
        Role::where('name', 'manager')->first()
            ->givePermissionTo(Permission::firstOrCreate(['name' => 'export-attendance']));
        Excel::fake();
        Excel::matchByRegex();

        $this->actingAs($this->manager)->get(route('attendance-logs.export', $query))->assertOk();

        $ids = [];
        Excel::assertDownloaded('/^bao-cao-cham-cong_.*\.xlsx$/', function (AttendanceLogsExport $export) use (&$ids) {
            $ids = $export->view()->getData()['logs']->pluck('employee_id')->sort()->values()->all();
            return true;
        });

        return $ids;
    }

    public function test_export_applies_current_employee_filter(): void
    {
        AttendanceLog::create(['employee_id' => $this->employeeA->id, 'work_date' => now()->subDays(3)->toDateString(), 'check_in_at' => now()->subDays(3)]);

        $ids = $this->exportedEmployeeIds(['employee_id' => $this->employeeA->id]);

        // Cả 2 ngày của nhân viên A (lọc nhân viên → không còn giới hạn "hôm nay"), không có B
        $this->assertSame([$this->employeeA->id, $this->employeeA->id], $ids);
    }

    public function test_export_without_filters_matches_default_today_view(): void
    {
        AttendanceLog::create(['employee_id' => $this->employeeA->id, 'work_date' => now()->subDays(3)->toDateString(), 'check_in_at' => now()->subDays(3)]);

        $ids = $this->exportedEmployeeIds([]);

        // Trang mặc định chỉ hiện hôm nay → file xuất cũng chỉ có 2 lượt của hôm nay
        $this->assertSame([$this->employeeA->id, $this->employeeB->id], $ids);
    }

    public function test_export_with_view_all_includes_all_dates(): void
    {
        AttendanceLog::create(['employee_id' => $this->employeeA->id, 'work_date' => now()->subDays(3)->toDateString(), 'check_in_at' => now()->subDays(3)]);

        $ids = $this->exportedEmployeeIds(['all' => 1]);

        $this->assertCount(3, $ids);
    }

    public function test_export_form_carries_current_filters(): void
    {
        Role::where('name', 'manager')->first()
            ->givePermissionTo(Permission::firstOrCreate(['name' => 'export-attendance']));

        $response = $this->actingAs($this->manager)->get(route('attendance-logs.index', [
            'employee_id' => $this->employeeA->id,
            'date_from'   => '2026-01-01',
        ]));

        $response->assertOk();
        $response->assertSee('<input type="hidden" name="employee_id" value="' . $this->employeeA->id . '">', false);
        $response->assertSee('<input type="hidden" name="date_from" value="2026-01-01">', false);
        $response->assertDontSee('<input type="hidden" name="all" value="1">', false);
    }

    public function test_user_without_export_permission_cannot_export_attendance_logs(): void
    {
        $response = $this->actingAs($this->manager)->get(route('attendance-logs.export'));
        $response->assertStatus(403);
    }

    // ── Sửa bản ghi chấm công (chỉ admin) ───────────────────────────────

    public function test_admin_can_edit_attendance_log_check_in_and_check_out(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'edit-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->first();

        $response = $this->actingAs($admin)->put(route('attendance-logs.update', $log), [
            'check_in_at'  => '08:15',
            'check_out_at' => '17:05',
        ]);

        $response->assertRedirect();
        $log->refresh();
        $this->assertEquals('08:15', $log->check_in_at->format('H:i'));
        $this->assertEquals('17:05', $log->check_out_at->format('H:i'));
        $this->assertEquals('manual', $log->check_in_method);
        $this->assertEquals('manual', $log->check_out_method);
    }

    public function test_admin_editing_check_out_for_overnight_shift_rolls_over_to_next_day(): void
    {
        // Tái hiện bug thực tế: ca "Ca Bếp tối" 18h-24h, sửa giờ ra thành "00:10" (rạng sáng hôm
        // sau) phải được lưu vào ĐÚNG NGÀY HÔM SAU work_date, không phải cùng ngày với giờ vào.
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'edit-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $shift = Shift::create([
            'code' => 'CA-BEP-TOI', 'name' => 'Ca Bếp tối', 'start_time' => '18:00', 'end_time' => '00:00',
            'work_mode' => 'onsite',
        ]);
        $workDate = now()->toDateString();
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employeeA->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);
        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->first();
        $log->update(['shift_schedule_id' => $schedule->id]);

        $response = $this->actingAs($admin)->put(route('attendance-logs.update', $log), [
            'check_in_at'  => '17:57',
            'check_out_at' => '00:10',
        ]);

        $response->assertRedirect();
        $log->refresh();
        $this->assertEquals($workDate . ' 17:57', $log->check_in_at->format('Y-m-d H:i'));
        $this->assertEquals(
            now()->parse($workDate)->addDay()->format('Y-m-d') . ' 00:10',
            $log->check_out_at->format('Y-m-d H:i')
        );
        $this->assertEquals(6.0, $log->netWorkedHours());
    }

    public function test_admin_editing_check_out_far_from_overnight_shift_window_stays_same_day(): void
    {
        // Tái hiện bug thực tế: ca "Ca Bar tối" 17h-01h (qua đêm), nhưng nhân viên thực chấm công
        // 11:00-15:00 (hoàn toàn ngoài khung giờ ca gốc). Vì giờ ra 15:00 <= giờ BẮT ĐẦU CA (17:00),
        // code cũ hiểu nhầm là "rạng sáng hôm sau" và đẩy check_out_at sang work_date+1 dù giờ ra
        // thực tế (15:00) đã sau giờ vào thực tế (11:00) trong CÙNG một ngày — khiến
        // netWorkedHours()/computeCong() bị kẹp (clamp) sai theo khung giờ ca gốc, luôn ra đúng
        // 8h/1 công bất kể nhân viên chỉ làm 4 tiếng thực tế.
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'edit-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $shift = Shift::create([
            'code' => 'CA-BAR-TOI', 'name' => 'Ca Bar tối', 'start_time' => '17:00', 'end_time' => '01:00',
            'work_mode' => 'onsite', 'shift_type' => 'parttime', 'standard_work_hours' => 8,
        ]);
        $workDate = now()->toDateString();
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employeeA->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);
        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->first();
        $log->update(['shift_schedule_id' => $schedule->id]);

        $response = $this->actingAs($admin)->put(route('attendance-logs.update', $log), [
            'check_in_at'  => '11:00',
            'check_out_at' => '15:00',
        ]);

        $response->assertRedirect();
        $log->refresh();
        $this->assertEquals($workDate . ' 11:00', $log->check_in_at->format('Y-m-d H:i'));
        $this->assertEquals($workDate . ' 15:00', $log->check_out_at->format('Y-m-d H:i'));
    }

    public function test_admin_can_clear_check_out_time(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'edit-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->first();
        $log->update(['check_out_at' => now()->addHours(8)]);

        $this->actingAs($admin)->put(route('attendance-logs.update', $log), [
            'check_in_at'  => '08:00',
            'check_out_at' => '',
        ])->assertRedirect();

        $log->refresh();
        $this->assertNull($log->check_out_at);
        $this->assertNull($log->check_out_method);
    }

    public function test_manager_with_view_attendance_permission_cannot_edit_attendance_log(): void
    {
        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->first();

        $response = $this->actingAs($this->manager)->put(route('attendance-logs.update', $log), [
            'check_in_at' => '08:15',
        ]);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_edit_attendance_log(): void
    {
        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->first();

        $response = $this->put(route('attendance-logs.update', $log), ['check_in_at' => '08:15']);

        $response->assertRedirect(route('login'));
    }

    // ── Xoá bản ghi chấm công (chỉ admin) ───────────────────────────────

    public function test_admin_can_delete_attendance_log(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'delete-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->first();

        $response = $this->actingAs($admin)->delete(route('attendance-logs.destroy', $log));

        $response->assertRedirect();
        $this->assertDatabaseMissing('attendance_logs', ['id' => $log->id]);
    }

    public function test_manager_with_view_attendance_permission_cannot_delete_attendance_log(): void
    {
        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->first();

        $response = $this->actingAs($this->manager)->delete(route('attendance-logs.destroy', $log));

        $response->assertStatus(403);
        $this->assertDatabaseHas('attendance_logs', ['id' => $log->id]);
    }

    public function test_guest_cannot_delete_attendance_log(): void
    {
        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->first();

        $response = $this->delete(route('attendance-logs.destroy', $log));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('attendance_logs', ['id' => $log->id]);
    }

    public function test_deleting_from_report_also_removes_it_from_employees_own_attendance_history(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'delete-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-own-attendance']));
        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');
        $this->employeeA->update(['user_id' => $staffUser->id]);

        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->first();

        // Trước khi xoá — nhân viên thấy bản ghi trong lịch sử chấm công cá nhân.
        $this->actingAs($staffUser)->get(route('my-attendance-logs.index'))
            ->assertStatus(200)
            ->assertSee($log->work_date->format('d/m/Y'));

        $this->actingAs($admin)->delete(route('attendance-logs.destroy', $log))->assertRedirect();

        // Sau khi xoá ở báo cáo — cùng bản ghi cũng biến mất khỏi lịch sử chấm công cá nhân.
        $this->actingAs($staffUser)->get(route('my-attendance-logs.index'))
            ->assertStatus(200)
            ->assertDontSee($log->work_date->format('d/m/Y'));
    }

    // ── Chấm công hộ / tạo mới (chỉ admin) ──────────────────────────────

    public function test_admin_can_create_attendance_log_on_behalf_of_employee(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $yesterday = now()->subDay()->toDateString();

        $response = $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id'  => $this->employeeA->id,
            'work_date'    => $yesterday,
            'check_in_at'  => '08:10',
            'check_out_at' => '17:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id'      => $this->employeeA->id,
            'work_date'        => $yesterday,
            'check_in_method'  => 'manual',
            'check_out_method' => 'manual',
        ]);
        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->where('work_date', $yesterday)->first();
        $this->assertEquals('08:10', $log->check_in_at->format('H:i'));
        $this->assertEquals('17:00', $log->check_out_at->format('H:i'));
    }

    public function test_admin_can_create_attendance_log_with_only_check_in(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $yesterday = now()->subDay()->toDateString();

        $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id' => $this->employeeA->id,
            'work_date'   => $yesterday,
            'check_in_at' => '08:10',
        ])->assertRedirect();

        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->where('work_date', $yesterday)->first();
        $this->assertNotNull($log);
        $this->assertNull($log->check_out_at);
    }

    public function test_creating_attendance_log_computes_late_minutes_from_assigned_shift(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $yesterday = now()->subDay()->toDateString();
        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính',
            'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite', 'grace_late_minutes' => 5,
        ]);
        ShiftSchedule::create([
            'employee_id' => $this->employeeA->id, 'shift_id' => $shift->id,
            'work_date' => $yesterday, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id' => $this->employeeA->id,
            'work_date'   => $yesterday,
            'check_in_at' => '08:20', // trễ 20 phút, trừ 5 phút ân hạn = 15 phút
        ])->assertRedirect();

        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->where('work_date', $yesterday)->first();
        $this->assertEquals(15, $log->late_minutes);
    }

    public function test_creating_attendance_log_for_overnight_shift_rolls_check_out_to_next_day(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $yesterday = now()->subDay()->toDateString();
        $shift = Shift::create([
            'code' => 'CA-BEP-TOI2', 'name' => 'Ca Bếp tối', 'start_time' => '18:00', 'end_time' => '00:00',
            'work_mode' => 'onsite',
        ]);
        ShiftSchedule::create([
            'employee_id' => $this->employeeB->id, 'shift_id' => $shift->id,
            'work_date' => $yesterday, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id'  => $this->employeeB->id,
            'work_date'    => $yesterday,
            'check_in_at'  => '17:57',
            'check_out_at' => '00:10',
        ])->assertRedirect();

        $log = AttendanceLog::where('employee_id', $this->employeeB->id)->where('work_date', $yesterday)->first();
        $this->assertEquals($yesterday . ' 17:57', $log->check_in_at->format('Y-m-d H:i'));
        $this->assertEquals(
            \Carbon\Carbon::parse($yesterday)->addDay()->format('Y-m-d') . ' 00:10',
            $log->check_out_at->format('Y-m-d H:i')
        );
        $this->assertEquals(6.0, $log->netWorkedHours());
    }

    public function test_marking_on_time_attendance_sets_check_in_and_out_to_shift_window_with_no_late_or_early(): void
    {
        // Mô phỏng đúng payload nút "Đúng giờ" (shift-day-detail-modal.blade.php) gửi lên: giờ
        // vào/ra = đúng effectiveShift()->start_time/end_time, không qua modal xác nhận.
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $yesterday = now()->subDay()->toDateString();
        $shift = Shift::create([
            'code' => 'CA-HC-OT', 'name' => 'Ca hành chính',
            'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite', 'grace_late_minutes' => 5,
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employeeA->id, 'shift_id' => $shift->id,
            'work_date' => $yesterday, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id'       => $this->employeeA->id,
            'work_date'         => $yesterday,
            'shift_schedule_id' => $schedule->id,
            'check_in_at'       => '08:00',
            'check_out_at'      => '17:00',
        ])->assertRedirect();

        $log = AttendanceLog::where('employee_id', $this->employeeA->id)->where('work_date', $yesterday)->first();
        $this->assertEquals($yesterday . ' 08:00', $log->check_in_at->format('Y-m-d H:i'));
        $this->assertEquals($yesterday . ' 17:00', $log->check_out_at->format('Y-m-d H:i'));
        $this->assertEquals(0, $log->late_minutes);
        $this->assertEquals(0, $log->early_minutes);
        $this->assertEquals(9.0, $log->netWorkedHours());
        // Ca 08:00-17:00 không có break_minutes = 9h làm việc, giờ chuẩn mặc định 8h (shift không
        // set standard_work_hours) => 9/8 = 1.125 => 1.13 công — công luôn tính theo giờ thực tế,
        // kể cả ca "fulltime" (mặc định của Shift::create() khi không truyền shift_type).
        $this->assertEquals(1.13, $log->computeCong());
    }

    public function test_marking_on_time_attendance_for_overnight_shift_rolls_check_out_to_next_day(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $yesterday = now()->subDay()->toDateString();
        $shift = Shift::create([
            'code' => 'CA-BAR-OT', 'name' => 'Ca Bar tối', 'start_time' => '17:00', 'end_time' => '01:00',
            'work_mode' => 'onsite', 'shift_type' => 'parttime', 'standard_work_hours' => 8,
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employeeB->id, 'shift_id' => $shift->id,
            'work_date' => $yesterday, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id'       => $this->employeeB->id,
            'work_date'         => $yesterday,
            'shift_schedule_id' => $schedule->id,
            'check_in_at'       => '17:00',
            'check_out_at'      => '01:00',
        ])->assertRedirect();

        $log = AttendanceLog::where('employee_id', $this->employeeB->id)->where('work_date', $yesterday)->first();
        $this->assertEquals($yesterday . ' 17:00', $log->check_in_at->format('Y-m-d H:i'));
        $this->assertEquals(
            \Carbon\Carbon::parse($yesterday)->addDay()->format('Y-m-d') . ' 01:00',
            $log->check_out_at->format('Y-m-d H:i')
        );
        $this->assertEquals(0, $log->late_minutes);
        $this->assertEquals(0, $log->early_minutes);
        $this->assertEquals(8.0, $log->netWorkedHours());
        $this->assertEquals(1.0, $log->computeCong());
    }

    public function test_cannot_create_duplicate_attendance_log_for_same_employee_and_date(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // employeeA đã có 1 bản ghi hôm nay từ setUp().
        $response = $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id' => $this->employeeA->id,
            'work_date'   => now()->toDateString(),
            'check_in_at' => '08:00',
        ]);

        $response->assertSessionHasErrors('employee_id');
        $this->assertEquals(1, AttendanceLog::where('employee_id', $this->employeeA->id)->count());
    }

    public function test_create_attendance_log_requires_at_least_one_time_field(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id' => $this->employeeB->id,
            'work_date'   => now()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors(['check_in_at', 'check_out_at']);
    }

    public function test_cannot_create_attendance_log_for_future_date(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id' => $this->employeeB->id,
            'work_date'   => now()->addDay()->toDateString(),
            'check_in_at' => '08:00',
        ]);

        $response->assertSessionHasErrors('work_date');
    }

    public function test_manager_with_view_attendance_permission_cannot_create_attendance_log(): void
    {
        $response = $this->actingAs($this->manager)->post(route('attendance-logs.store'), [
            'employee_id' => $this->employeeB->id,
            'work_date'   => now()->subDay()->toDateString(),
            'check_in_at' => '08:00',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_sees_create_attendance_log_button_and_modal_on_index_page(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-attendance-logs']));
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-attendance']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('attendance-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('Chấm công hộ');
        $response->assertSee('id="createAttendanceLogModal"', false);
    }

    public function test_guest_cannot_create_attendance_log(): void
    {
        $response = $this->post(route('attendance-logs.store'), [
            'employee_id' => $this->employeeB->id,
            'work_date'   => now()->subDay()->toDateString(),
            'check_in_at' => '08:00',
        ]);

        $response->assertRedirect(route('login'));
    }

    // ── Chọn ca khi chấm công hộ ─────────────────────────────────────────

    private function makeAdminForCreate(): User
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-attendance-logs']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_employee_shifts_endpoint_lists_schedules_with_time_range_and_log_flag(): void
    {
        $admin = $this->makeAdminForCreate();
        $yesterday = now()->subDay()->toDateString();

        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính',
            'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employeeB->id, 'shift_id' => $shift->id,
            'work_date' => $yesterday, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $response = $this->actingAs($admin)->getJson(route('attendance-logs.employee-shifts', [
            'employee_id' => $this->employeeB->id, 'work_date' => $yesterday,
        ]));

        $response->assertOk();
        $response->assertJson(['data' => [[
            'id' => $schedule->id, 'label' => 'Ca hành chính (08:00–17:00)', 'has_attendance_log' => false,
        ]]]);
    }

    public function test_create_requires_selecting_shift_when_employee_has_multiple_shifts_that_day(): void
    {
        $admin = $this->makeAdminForCreate();
        $yesterday = now()->subDay()->toDateString();

        $shiftA = Shift::create(['code' => 'CA-A', 'name' => 'Ca sáng', 'start_time' => '08:00', 'end_time' => '12:00', 'work_mode' => 'onsite']);
        $shiftB = Shift::create(['code' => 'CA-B', 'name' => 'Ca chiều', 'start_time' => '13:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        ShiftSchedule::create(['employee_id' => $this->employeeB->id, 'shift_id' => $shiftA->id, 'work_date' => $yesterday, 'assignment_type' => 'rotation', 'status' => 'scheduled']);
        $scheduleB = ShiftSchedule::create(['employee_id' => $this->employeeB->id, 'shift_id' => $shiftB->id, 'work_date' => $yesterday, 'assignment_type' => 'rotation', 'status' => 'scheduled']);

        // Không chọn ca — nhân viên có 2 ca cùng ngày, phải báo lỗi yêu cầu chọn.
        $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id' => $this->employeeB->id, 'work_date' => $yesterday, 'check_in_at' => '13:05',
        ])->assertSessionHasErrors('shift_schedule_id');
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $this->employeeB->id, 'work_date' => $yesterday]);

        // Chọn đúng ca chiều — tạo thành công, gắn đúng shift_schedule_id.
        $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id' => $this->employeeB->id, 'work_date' => $yesterday,
            'shift_schedule_id' => $scheduleB->id, 'check_in_at' => '13:05',
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employeeB->id, 'work_date' => $yesterday, 'shift_schedule_id' => $scheduleB->id,
        ]);
    }

    public function test_cannot_use_shift_schedule_id_belonging_to_a_different_employee(): void
    {
        $admin = $this->makeAdminForCreate();
        $yesterday = now()->subDay()->toDateString();

        $shift = Shift::create(['code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        $scheduleOfB = ShiftSchedule::create([
            'employee_id' => $this->employeeB->id, 'shift_id' => $shift->id,
            'work_date' => $yesterday, 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        // Chọn nhân viên A nhưng gửi kèm shift_schedule_id thuộc về nhân viên B.
        $response = $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'employee_id' => $this->employeeA->id, 'work_date' => $yesterday,
            'shift_schedule_id' => $scheduleOfB->id, 'check_in_at' => '08:05',
        ]);

        $response->assertSessionHasErrors('shift_schedule_id');
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $this->employeeA->id, 'work_date' => $yesterday]);
    }
}
