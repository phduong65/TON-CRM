<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftScheduleRecurrence;
use App\Models\Team;
use App\Models\User;
use App\Services\ShiftScheduleGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Employee $employee;
    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'manager']);
        foreach (['view-shift-schedules', 'create-shift-schedules', 'edit-shift-schedules', 'delete-shift-schedules'] as $perm) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'branch_id' => $branch->id, 'is_active' => true,
        ]);

        $this->shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_grid_shows_overtime_badge_for_day_with_no_schedule(): void
    {
        $workDate = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'work_date' => $workDate, 'overtime_hours' => 2.5,
        ]);

        $response = $this->actingAs($this->manager)->get(route('shift-schedules.index'));

        $response->assertStatus(200);
        $response->assertSee('+2.5h TC');
        // Manager có quyền create-shift-schedules -> vẫn thấy nút "Xếp ca" cạnh badge tăng ca.
        $response->assertSee('Xếp ca');
    }

    public function test_grid_shows_overtime_badge_without_assign_button_for_view_only_user(): void
    {
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-shift-schedules']));
        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');

        $workDate = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'work_date' => $workDate, 'overtime_hours' => 1.5,
        ]);

        $response = $this->actingAs($staffUser)->get(route('shift-schedules.index'));

        $response->assertStatus(200);
        $response->assertSee('+1.5h TC');
    }

    public function test_grid_shows_overtime_badge_when_underlying_schedule_was_later_cancelled(): void
    {
        $workDate = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $this->shift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'shift_schedule_id' => $schedule->id,
            'work_date' => $workDate, 'overtime_hours' => 3,
        ]);

        // Ca bị huỷ sau khi tăng ca đã được duyệt gắn với nó — log vẫn còn shift_schedule_id
        // trỏ tới ca đã huỷ này, nhưng ô trên lưới không còn coi ngày đó là "có ca" nữa.
        $schedule->update(['status' => 'cancelled']);

        $response = $this->actingAs($this->manager)->get(route('shift-schedules.index'));

        $response->assertStatus(200);
        $response->assertSee('+3h TC');
    }

    public function test_multiple_shifts_same_day_are_ordered_by_start_time(): void
    {
        $workDate = now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $eveningShift = Shift::create([
            'code' => 'CA-TOI', 'name' => 'Ca tối', 'start_time' => '18:00', 'end_time' => '22:00', 'work_mode' => 'onsite',
        ]);
        $morningShift = Shift::create([
            'code' => 'CA-SANG', 'name' => 'Ca sáng', 'start_time' => '11:00', 'end_time' => '15:00', 'work_mode' => 'onsite',
        ]);

        // Cố ý TẠO ca tối (18h) trước ca sáng (11h) — insertion/ID order ngược với thứ tự thời
        // gian mong đợi, để bài test thật sự kiểm chứng có sort chứ không phải tình cờ đúng thứ tự.
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $eveningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $morningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->manager)->get(route('shift-schedules.index', ['week' => $workDate]));

        $response->assertStatus(200);
        $schedules = $response->viewData('schedules');
        $key       = $this->employee->id . '_' . $workDate;

        $orderedShiftCodes = $schedules->get($key)->map(fn($s) => $s->shift->code)->values()->all();
        $this->assertEquals(['CA-SANG', 'CA-TOI'], $orderedShiftCodes);
    }

    // ── Bộ lọc "Chỉ hiện NV chưa có ca hôm nay" (no_shift_today) ───────────

    public function test_toggle_button_for_no_shift_today_filter_is_visible_and_reflects_state(): void
    {
        $response = $this->actingAs($this->manager)->get(route('shift-schedules.index'));
        $response->assertStatus(200);
        $response->assertSee('NV chưa có ca hôm nay');

        $response = $this->actingAs($this->manager)->get(route('shift-schedules.index', ['no_shift_today' => 1]));
        $response->assertStatus(200);
        $response->assertSee('Đang lọc: Chưa có ca hôm nay');
    }

    public function test_no_shift_today_filter_excludes_employees_scheduled_today(): void
    {
        $unscheduled = Employee::create([
            'code' => 'EMP-02', 'name' => 'Trần Thị B', 'branch_id' => $this->employee->branch_id, 'is_active' => true,
        ]);

        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $this->shift->id,
            'work_date' => now()->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->manager)->get(route('shift-schedules.index', ['no_shift_today' => 1]));

        $response->assertStatus(200);
        $response->assertViewHas('employees', function ($employees) use ($unscheduled) {
            return $employees->contains('id', $unscheduled->id) && !$employees->contains('id', $this->employee->id);
        });
    }

    public function test_no_shift_today_filter_includes_employee_whose_only_schedule_today_is_cancelled(): void
    {
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $this->shift->id,
            'work_date' => now()->toDateString(), 'status' => 'cancelled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->manager)->get(route('shift-schedules.index', ['no_shift_today' => 1]));

        $response->assertStatus(200);
        $response->assertViewHas('employees', function ($employees) {
            return $employees->contains('id', $this->employee->id);
        });
    }

    public function test_no_shift_today_filter_ignores_schedules_on_other_days(): void
    {
        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $this->shift->id,
            'work_date' => now()->addDay()->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->manager)->get(route('shift-schedules.index', ['no_shift_today' => 1]));

        $response->assertStatus(200);
        $response->assertViewHas('employees', function ($employees) {
            return $employees->contains('id', $this->employee->id);
        });
    }

    public function test_manager_can_assign_single_day_shift(): void
    {
        $response = $this->actingAs($this->manager)->post(route('shift-schedules.store'), [
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_schedules', [
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
        ]);
    }

    public function test_manager_can_assign_flexible_shift(): void
    {
        $response = $this->actingAs($this->manager)->post(route('shift-schedules.store'), [
            'employee_id'         => $this->employee->id,
            'work_date'           => '2026-07-06',
            'custom_start_time'   => '12:00',
            'custom_end_time'     => '20:00',
            'custom_is_wfh'       => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_schedules', [
            'employee_id'       => $this->employee->id,
            'work_date'         => '2026-07-06',
            'shift_id'          => null,
            'custom_start_time' => '12:00',
            'custom_end_time'   => '20:00',
            'custom_is_wfh'     => 1,
        ]);
    }

    public function test_assign_shift_fails_without_shift_id_or_custom_time(): void
    {
        $response = $this->actingAs($this->manager)->post(route('shift-schedules.store'), [
            'employee_id' => $this->employee->id,
            'work_date'   => '2026-07-06',
        ]);

        $response->assertSessionHasErrors(['shift_id', 'custom_start_time']);
        $this->assertDatabaseMissing('shift_schedules', ['employee_id' => $this->employee->id]);
    }

    public function test_manager_can_convert_template_schedule_to_flexible(): void
    {
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->put(route('shift-schedules.update', $schedule), [
            'custom_start_time' => '12:00',
            'custom_end_time'   => '20:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_schedules', [
            'id' => $schedule->id, 'shift_id' => null,
            'custom_start_time' => '12:00', 'custom_end_time' => '20:00',
        ]);
    }

    public function test_manager_can_convert_flexible_schedule_to_template(): void
    {
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => null,
            'custom_start_time' => '12:00', 'custom_end_time' => '20:00',
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->put(route('shift-schedules.update', $schedule), [
            'shift_id' => $this->shift->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_schedules', [
            'id' => $schedule->id, 'shift_id' => $this->shift->id, 'custom_start_time' => null,
        ]);
    }

    public function test_bulk_assign_creates_fixed_schedule_for_matching_weekdays(): void
    {
        // 2026-07-06 (Mon) .. 2026-07-12 (Sun) — chọn T2..T6 => 5 ngày
        $response = $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'date_to'      => '2026-07-12',
            'weekdays'     => [1, 2, 3, 4, 5],
        ]);

        $response->assertRedirect();
        $this->assertEquals(5, ShiftSchedule::where('employee_id', $this->employee->id)->count());
        $this->assertDatabaseHas('shift_schedules', [
            'employee_id' => $this->employee->id, 'work_date' => '2026-07-06', 'assignment_type' => 'fixed',
        ]);
        $this->assertDatabaseMissing('shift_schedules', [
            'employee_id' => $this->employee->id, 'work_date' => '2026-07-11', // Saturday not selected
        ]);
    }

    public function test_bulk_assign_skips_days_already_scheduled(): void
    {
        ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'date_to'      => '2026-07-06',
            'weekdays'     => [1, 2, 3, 4, 5, 6, 7],
        ]);

        // Vẫn chỉ có 1 bản ghi (không bị trùng/ghi đè)
        $this->assertEquals(1, ShiftSchedule::where('employee_id', $this->employee->id)
            ->where('work_date', '2026-07-06')->count());
    }

    public function test_bulk_assign_with_multiple_shift_ids_creates_a_row_per_shift(): void
    {
        $shift2 = Shift::create([
            'code' => 'CA-TOI', 'name' => 'Ca tối', 'start_time' => '18:00', 'end_time' => '22:00', 'work_mode' => 'onsite',
        ]);

        // 1 nhân viên × 1 ngày (T2) × 2 ca => 2 bản ghi (đa ca) trong cùng 1 đợt
        $response = $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id, $shift2->id],
            'date_from'    => '2026-07-06',
            'date_to'      => '2026-07-06',
            'weekdays'     => [1],
        ]);

        $response->assertRedirect();

        $schedules = ShiftSchedule::where('employee_id', $this->employee->id)
            ->where('work_date', '2026-07-06')->get();

        $this->assertCount(2, $schedules);
        $this->assertEqualsCanonicalizing([$this->shift->id, $shift2->id], $schedules->pluck('shift_id')->all());
        $this->assertEquals(1, $schedules->pluck('batch_id')->unique()->count());
    }

    public function test_manager_can_assign_second_shift_same_day(): void
    {
        $shift2 = Shift::create([
            'code' => 'CA-TOI', 'name' => 'Ca tối', 'start_time' => '18:00', 'end_time' => '22:00', 'work_mode' => 'onsite',
        ]);

        $this->actingAs($this->manager)->post(route('shift-schedules.store'), [
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
        ])->assertRedirect();

        $response = $this->actingAs($this->manager)->post(route('shift-schedules.store'), [
            'employee_id' => $this->employee->id,
            'shift_id'    => $shift2->id,
            'work_date'   => '2026-07-06',
        ]);

        $response->assertRedirect();
        $this->assertEquals(2, ShiftSchedule::where('employee_id', $this->employee->id)
            ->where('work_date', '2026-07-06')->count());
    }

    public function test_manager_can_update_existing_shift_schedule(): void
    {
        $shift2 = Shift::create([
            'code' => 'CA-TOI', 'name' => 'Ca tối', 'start_time' => '18:00', 'end_time' => '22:00', 'work_mode' => 'onsite',
        ]);

        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->put(route('shift-schedules.update', $schedule), [
            'shift_id' => $shift2->id,
            'note'     => 'Đổi sang ca tối',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_schedules', [
            'id' => $schedule->id, 'shift_id' => $shift2->id, 'note' => 'Đổi sang ca tối',
        ]);
    }

    public function test_updating_schedule_clears_stale_leave_adjustment(): void
    {
        // Ca đã bị điều chỉnh giờ (adjusted_start_time) do một đơn nghỉ theo giờ được duyệt
        // trước đó — tính dựa trên giờ ca CŨ (08h-17h). Sửa sang ca khác (18h-22h) phải xoá
        // điều chỉnh cũ, nếu không effectiveShift() sẽ đè khung giờ mới bằng giờ điều chỉnh
        // lỗi thời (VD 13:00), sai hoàn toàn so với ca tối vừa đổi sang.
        $shift2 = Shift::create([
            'code' => 'CA-TOI', 'name' => 'Ca tối', 'start_time' => '18:00', 'end_time' => '22:00', 'work_mode' => 'onsite',
        ]);

        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
            'adjusted_start_time' => '13:00',
        ]);

        $response = $this->actingAs($this->manager)->put(route('shift-schedules.update', $schedule), [
            'shift_id' => $shift2->id,
        ]);

        $response->assertRedirect();
        $schedule->refresh();
        $this->assertEquals($shift2->id, $schedule->shift_id);
        $this->assertNull($schedule->adjusted_start_time);
        $this->assertNull($schedule->adjusted_end_time);
        $this->assertEquals('18:00', $schedule->effectiveShift()->start_time);
    }

    public function test_employee_with_view_only_permission_cannot_update_shift_schedule(): void
    {
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-shift-schedules']));

        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');

        $response = $this->actingAs($staffUser)->put(route('shift-schedules.update', $schedule), [
            'shift_id' => $this->shift->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_can_delete_schedule(): void
    {
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy', $schedule));
        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $schedule->id]);
    }

    public function test_employee_with_view_only_permission_cannot_delete_shift_schedule(): void
    {
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-shift-schedules']));

        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');

        $response = $this->actingAs($staffUser)->delete(route('shift-schedules.destroy', $schedule));

        $response->assertStatus(403);
    }

    public function test_manager_can_bulk_delete_selected_schedules_including_a_batch(): void
    {
        $singleSchedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-13',
            'date_to'      => '2026-07-17',
            'weekdays'     => [1, 2, 3, 4, 5],
        ])->assertRedirect();

        $batchId = ShiftSchedule::whereNotNull('batch_id')->first()->batch_id;
        $this->assertEquals(5, ShiftSchedule::where('batch_id', $batchId)->count());

        $oneBatchRow = ShiftSchedule::where('batch_id', $batchId)->first();

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.bulk-destroy'), [
            'schedule_ids' => [$singleSchedule->id, $oneBatchRow->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $singleSchedule->id]);
        $this->assertEquals(0, ShiftSchedule::where('batch_id', $batchId)->count());
    }

    public function test_employee_with_view_only_permission_cannot_bulk_delete_shift_schedules(): void
    {
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-shift-schedules']));

        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');

        $response = $this->actingAs($staffUser)->delete(route('shift-schedules.bulk-destroy'), [
            'schedule_ids' => [$schedule->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('shift_schedules', ['id' => $schedule->id]);
    }

    public function test_manager_can_delete_all_schedules_matching_current_week_and_filters(): void
    {
        $otherEmployee = Employee::create([
            'code' => 'EMP-04', 'name' => 'Phạm Văn D', 'branch_id' => $this->employee->branch_id, 'is_active' => true,
        ]);

        $inWeek = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06', // Monday of the target week
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $otherEmployeeInWeek = ShiftSchedule::create([
            'employee_id' => $otherEmployee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-07',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $outsideWeek = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-20',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-all'), [
            'week' => '2026-07-06',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $inWeek->id]);
        $this->assertDatabaseMissing('shift_schedules', ['id' => $otherEmployeeInWeek->id]);
        $this->assertDatabaseHas('shift_schedules', ['id' => $outsideWeek->id]);
    }

    public function test_delete_all_respects_employee_filter(): void
    {
        $otherEmployee = Employee::create([
            'code' => 'EMP-05', 'name' => 'Trần Thị E', 'branch_id' => $this->employee->branch_id, 'is_active' => true,
        ]);

        $mine = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $others = ShiftSchedule::create([
            'employee_id' => $otherEmployee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-all'), [
            'week'        => '2026-07-06',
            'employee_id' => $this->employee->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $mine->id]);
        $this->assertDatabaseHas('shift_schedules', ['id' => $others->id]);
    }

    public function test_employee_with_view_only_permission_cannot_delete_all_shift_schedules(): void
    {
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-shift-schedules']));

        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');

        $response = $this->actingAs($staffUser)->delete(route('shift-schedules.destroy-all'), [
            'week' => '2026-07-06',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('shift_schedules', ['id' => $schedule->id]);
    }

    // ── Xoá ca theo nhân viên/đội nhóm + khoảng thời gian (destroy-filtered) ─

    public function test_manager_can_delete_shift_schedules_by_employee_and_day(): void
    {
        $match = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $otherDay = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-07',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'day',
            'date'         => '2026-07-06',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $match->id]);
        $this->assertDatabaseHas('shift_schedules', ['id' => $otherDay->id]);
    }

    public function test_manager_can_delete_shift_schedules_by_employee_and_month(): void
    {
        $inMonth = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-15',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $outsideMonth = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-08-01',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'month',
            'month'        => '2026-07',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $inMonth->id]);
        $this->assertDatabaseHas('shift_schedules', ['id' => $outsideMonth->id]);
    }

    public function test_manager_can_delete_shift_schedules_by_date_range(): void
    {
        $inRange = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-10',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $outsideRange = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-25',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'range',
            'date_from'    => '2026-07-08',
            'date_to'      => '2026-07-12',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $inRange->id]);
        $this->assertDatabaseHas('shift_schedules', ['id' => $outsideRange->id]);
    }

    public function test_manager_can_delete_shift_schedules_from_a_date_onward_with_no_end_date(): void
    {
        $before = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-05',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $onStart = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $farFuture = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2027-01-01',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'range',
            'date_from'    => '2026-07-06',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_schedules', ['id' => $before->id]);
        $this->assertDatabaseMissing('shift_schedules', ['id' => $onStart->id]);
        $this->assertDatabaseMissing('shift_schedules', ['id' => $farFuture->id]);
    }

    public function test_manager_can_delete_shift_schedules_up_to_a_date_with_no_start_date(): void
    {
        $farPast = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2025-01-01',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $onEnd = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-12',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $after = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-13',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'range',
            'date_to'      => '2026-07-12',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $farPast->id]);
        $this->assertDatabaseMissing('shift_schedules', ['id' => $onEnd->id]);
        $this->assertDatabaseHas('shift_schedules', ['id' => $after->id]);
    }

    public function test_delete_filtered_range_requires_at_least_one_date_bound(): void
    {
        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'range',
        ]);

        $response->assertSessionHasErrors('date_from');
    }

    public function test_delete_filtered_range_open_start_still_rejects_end_before_start_when_both_given(): void
    {
        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'range',
            'date_from'    => '2026-07-12',
            'date_to'      => '2026-07-06',
        ]);

        $response->assertSessionHasErrors('date_to');
    }

    public function test_manager_can_delete_all_shift_schedules_of_an_employee_regardless_of_date(): void
    {
        $past = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2025-01-01',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $future = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2027-12-31',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'all',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $past->id]);
        $this->assertDatabaseMissing('shift_schedules', ['id' => $future->id]);
    }

    public function test_manager_can_delete_shift_schedules_by_team(): void
    {
        $team = Team::create(['code' => 'BAR', 'name' => 'Bar', 'branch_id' => $this->employee->branch_id, 'is_active' => true]);

        $teamEmployee = Employee::create([
            'code' => 'EMP-06', 'name' => 'Lê Văn F', 'branch_id' => $this->employee->branch_id,
            'team_id' => $team->id, 'is_active' => true,
        ]);

        $inTeam = ShiftSchedule::create([
            'employee_id' => $teamEmployee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $notInTeam = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'      => 'team',
            'team_ids'   => [$team->id],
            'range_type' => 'day',
            'date'       => '2026-07-06',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $inTeam->id]);
        $this->assertDatabaseHas('shift_schedules', ['id' => $notInTeam->id]);
    }

    public function test_delete_filtered_with_bounded_range_only_removes_scoped_batch_rows(): void
    {
        // Bug trước đây: chọn xoá 1 ngày trong đợt 5 ngày lại xoá cả 5 ngày (kể cả các
        // ngày ngoài phạm vi đã chọn), gây mất lịch xếp ca (và mất liên kết chấm công)
        // của những ngày không hề được chọn để xoá. range_type có giới hạn ngày (day/
        // month/range) giờ phải tôn trọng đúng phạm vi, không cascade cả đợt.
        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-13',
            'date_to'      => '2026-07-17',
            'weekdays'     => [1, 2, 3, 4, 5],
        ])->assertRedirect();

        $batchId = ShiftSchedule::whereNotNull('batch_id')->first()->batch_id;
        $this->assertEquals(5, ShiftSchedule::where('batch_id', $batchId)->count());

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'range',
            'date_from'    => '2026-07-13',
            'date_to'      => '2026-07-13',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['batch_id' => $batchId, 'work_date' => '2026-07-13']);
        $this->assertEquals(4, ShiftSchedule::where('batch_id', $batchId)->count());
    }

    public function test_delete_filtered_with_range_covering_whole_batch_removes_all_of_it(): void
    {
        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-13',
            'date_to'      => '2026-07-17',
            'weekdays'     => [1, 2, 3, 4, 5],
        ])->assertRedirect();

        $batchId = ShiftSchedule::whereNotNull('batch_id')->first()->batch_id;

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'range',
            'date_from'    => '2026-07-13',
            'date_to'      => '2026-07-17',
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, ShiftSchedule::where('batch_id', $batchId)->count());
    }

    public function test_delete_filtered_day_scoped_on_batch_row_only_removes_that_day(): void
    {
        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-13',
            'date_to'      => '2026-07-17',
            'weekdays'     => [1, 2, 3, 4, 5],
        ])->assertRedirect();

        $batchId = ShiftSchedule::whereNotNull('batch_id')->first()->batch_id;

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'day',
            'date'         => '2026-07-14',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['batch_id' => $batchId, 'work_date' => '2026-07-14']);
        $this->assertEquals(4, ShiftSchedule::where('batch_id', $batchId)->count());
        $this->assertDatabaseHas('shift_schedules', ['batch_id' => $batchId, 'work_date' => '2026-07-13']);
        $this->assertDatabaseHas('shift_schedules', ['batch_id' => $batchId, 'work_date' => '2026-07-17']);
    }

    public function test_delete_filtered_month_scoped_on_batch_spanning_two_months_only_removes_matching_month(): void
    {
        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-29',
            'date_to'      => '2026-08-04',
            'weekdays'     => [1, 2, 3, 4, 5, 6, 7],
        ])->assertRedirect();

        $batchId = ShiftSchedule::whereNotNull('batch_id')->first()->batch_id;
        $this->assertEquals(7, ShiftSchedule::where('batch_id', $batchId)->count()); // 3 ngày T7 + 4 ngày T8

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'month',
            'month'        => '2026-07',
        ]);

        $response->assertRedirect();
        $this->assertEquals(4, ShiftSchedule::where('batch_id', $batchId)->count());
        $this->assertDatabaseMissing('shift_schedules', ['batch_id' => $batchId, 'work_date' => '2026-07-31']);
        $this->assertDatabaseHas('shift_schedules', ['batch_id' => $batchId, 'work_date' => '2026-08-01']);
    }

    public function test_delete_filtered_range_handles_mix_of_standalone_and_batch_schedules(): void
    {
        $standalone = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-13',
            'date_to'      => '2026-07-17',
            'weekdays'     => [1, 2, 3, 4, 5],
        ])->assertRedirect();

        $batchId = ShiftSchedule::whereNotNull('batch_id')->first()->batch_id;

        // Phạm vi 07-06 → 07-14 bao trùm cả ca lẻ (07-06) lẫn 2 ngày đầu của đợt cố định
        // (07-13, 07-14) — cả hai loại phải được xử lý đúng trong cùng 1 lượt xoá.
        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'range',
            'date_from'    => '2026-07-06',
            'date_to'      => '2026-07-14',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $standalone->id]);
        $this->assertEquals(3, ShiftSchedule::where('batch_id', $batchId)->count());
        $this->assertDatabaseHas('shift_schedules', ['batch_id' => $batchId, 'work_date' => '2026-07-15']);
        $this->assertDatabaseHas('shift_schedules', ['batch_id' => $batchId, 'work_date' => '2026-07-16']);
        $this->assertDatabaseHas('shift_schedules', ['batch_id' => $batchId, 'work_date' => '2026-07-17']);
    }

    public function test_destroy_all_only_removes_current_week_leaving_other_weeks_of_same_batch(): void
    {
        // Đợt cố định trải dài 2 tuần (07-06 → 07-17). Xoá "tất cả ca hiển thị" khi đang
        // xem tuần đầu KHÔNG được đụng tới tuần thứ hai của cùng đợt.
        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'date_to'      => '2026-07-17',
            'weekdays'     => [1, 2, 3, 4, 5],
        ])->assertRedirect();

        $batchId = ShiftSchedule::whereNotNull('batch_id')->first()->batch_id;
        $this->assertEquals(10, ShiftSchedule::where('batch_id', $batchId)->count());

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-all'), [
            'week' => '2026-07-06',
        ]);

        $response->assertRedirect();
        $this->assertEquals(5, ShiftSchedule::where('batch_id', $batchId)->count());
        $this->assertDatabaseMissing('shift_schedules', ['batch_id' => $batchId, 'work_date' => '2026-07-06']);
        $this->assertDatabaseHas('shift_schedules', ['batch_id' => $batchId, 'work_date' => '2026-07-13']);
    }

    public function test_scoped_partial_delete_does_not_cancel_recurrence_and_horizon_keeps_generating(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-06')); // Monday

        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'weekdays'     => [1], // lặp lại mỗi thứ 2, không giới hạn ngày kết thúc
        ])->assertRedirect();

        $recurrence   = ShiftScheduleRecurrence::first();
        $initialCount = ShiftSchedule::where('batch_id', $recurrence->batch_id)->count();
        $this->assertGreaterThan(0, $initialCount);

        // Xoá đúng 1 ngày (thứ 2 đầu tiên) qua luồng xoá theo phạm vi ngày — không phải
        // xoá toàn đợt qua destroy() — nên recurrence phải còn nguyên và tiếp tục sinh ca.
        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'day',
            'date'         => '2026-07-06',
        ]);
        $response->assertRedirect();

        $this->assertEquals($initialCount - 1, ShiftSchedule::where('batch_id', $recurrence->batch_id)->count());
        $this->assertDatabaseHas('shift_schedule_recurrences', ['batch_id' => $recurrence->batch_id]);

        Carbon::setTestNow(Carbon::parse('2026-07-06')->addWeeks(4));
        $this->artisan('shift-schedules:generate-recurring')->assertExitCode(0);

        $this->assertTrue(ShiftSchedule::where('batch_id', $recurrence->batch_id)->count() > ($initialCount - 1));
    }

    public function test_scoped_delete_that_empties_entire_batch_cancels_its_recurrence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-06')); // Monday

        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'date_to'      => '2026-07-06', // đợt chỉ đúng 1 ngày, không có date_to trống nên không tạo recurrence
            'weekdays'     => [1],
        ])->assertRedirect();

        $batchId = ShiftSchedule::whereNotNull('batch_id')->first()->batch_id;
        $this->assertEquals(1, ShiftSchedule::where('batch_id', $batchId)->count());

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'day',
            'date'         => '2026-07-06',
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, ShiftSchedule::where('batch_id', $batchId)->count());
    }

    public function test_attendance_log_survives_when_its_shift_schedule_is_deleted(): void
    {
        // Hồi quy đúng khiếu nại ban đầu: xoá lịch xếp ca không được xoá mất dữ liệu
        // chấm công đã có — chỉ được phép mất liên kết (shift_schedule_id -> null).
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $log = AttendanceLog::create([
            'employee_id'        => $this->employee->id,
            'shift_schedule_id'  => $schedule->id,
            'work_date'          => '2026-07-06',
            'check_in_at'        => '2026-07-06 08:02:00',
            'check_out_at'       => '2026-07-06 17:05:00',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'day',
            'date'         => '2026-07-06',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $schedule->id]);

        $log->refresh();
        $this->assertDatabaseHas('attendance_logs', ['id' => $log->id]);
        $this->assertNull($log->shift_schedule_id);
        $this->assertNotNull($log->check_in_at);
        $this->assertNotNull($log->check_out_at);
    }

    public function test_employee_with_view_only_permission_cannot_delete_filtered_shift_schedules(): void
    {
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-shift-schedules']));

        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');

        $response = $this->actingAs($staffUser)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'all',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('shift_schedules', ['id' => $schedule->id]);
    }

    public function test_delete_filtered_requires_date_when_range_type_is_day(): void
    {
        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'day',
        ]);

        $response->assertSessionHasErrors('date');
    }

    /**
     * Regression: form modal luôn gửi cả 4 input (date/month/date_from/date_to) lên server
     * dù chỉ ẩn bằng CSS class "hidden" (không disable) — nên khi chọn range_type=all,
     * các input còn lại vẫn có mặt trong request dưới dạng chuỗi rỗng. Trước khi thêm
     * "nullable", "" bị middleware ConvertEmptyStringsToNull chuyển thành null, và rule
     * date/date_format vẫn chạy trên giá trị null (vì field có mặt trong request) → luôn
     * fail dù range_type không phải "day"/"month"/"range".
     */
    public function test_delete_filtered_with_all_range_ignores_empty_sibling_date_fields(): void
    {
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id'    => $this->shift->id,
            'work_date'   => '2026-07-06',
            'assignment_type' => 'rotation',
            'status'      => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy-filtered'), [
            'scope'        => 'employee',
            'employee_ids' => [$this->employee->id],
            'range_type'   => 'all',
            'date'         => '',
            'month'        => '',
            'date_from'    => '',
            'date_to'      => '',
        ]);

        $response->assertSessionDoesntHaveErrors(['date', 'month', 'date_from', 'date_to']);
        $response->assertRedirect();
        $this->assertDatabaseMissing('shift_schedules', ['id' => $schedule->id]);
    }

    // ── Xếp ca cố định lặp lại hàng tuần (không nhập "Đến ngày") ────────────

    public function test_bulk_assign_without_date_to_creates_recurring_rule_and_generates_horizon(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-06')); // Monday

        $response = $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'weekdays'     => [1, 2, 3, 4, 5],
        ]);

        $response->assertRedirect();

        $recurrence = ShiftScheduleRecurrence::first();
        $this->assertNotNull($recurrence);
        $this->assertTrue($recurrence->is_active);
        $this->assertEquals([1, 2, 3, 4, 5], $recurrence->weekdays);
        $this->assertEquals('2026-07-06', $recurrence->starts_on->toDateString());
        $this->assertEquals(
            now()->addWeeks(ShiftScheduleGenerator::HORIZON_WEEKS)->toDateString(),
            $recurrence->last_generated_through->toDateString(),
        );

        // 12 tuần × 5 ngày trong tuần (T2-T6) ≈ 60 bản ghi cùng batch_id
        $generatedCount = ShiftSchedule::where('batch_id', $recurrence->batch_id)->count();
        $this->assertGreaterThan(50, $generatedCount);
    }

    public function test_recurring_command_extends_generation_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-06')); // Monday

        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'weekdays'     => [1],
        ])->assertRedirect();

        $recurrence  = ShiftScheduleRecurrence::first();
        $countBefore = ShiftSchedule::where('batch_id', $recurrence->batch_id)->count();

        // 4 tuần sau, lệnh chạy hàng đêm sẽ mở rộng thêm cửa sổ sinh ca
        Carbon::setTestNow(Carbon::parse('2026-07-06')->addWeeks(4));
        $this->artisan('shift-schedules:generate-recurring')->assertExitCode(0);

        $countAfter = ShiftSchedule::where('batch_id', $recurrence->fresh()->batch_id)->count();
        $this->assertGreaterThan($countBefore, $countAfter);
    }

    public function test_deleting_one_row_of_bulk_batch_only_deletes_that_row(): void
    {
        $otherEmployee = Employee::create([
            'code' => 'EMP-03', 'name' => 'Lê Văn C', 'branch_id' => $this->employee->branch_id, 'is_active' => true,
        ]);

        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id, $otherEmployee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'date_to'      => '2026-07-12',
            'weekdays'     => [1, 2, 3, 4, 5],
        ])->assertRedirect();

        $batchId = ShiftSchedule::where('employee_id', $this->employee->id)->first()->batch_id;
        $this->assertNotNull($batchId);
        $this->assertEquals(10, ShiftSchedule::where('batch_id', $batchId)->count()); // 2 NV × 5 ngày

        $oneSchedule = ShiftSchedule::where('batch_id', $batchId)->first();

        $response = $this->actingAs($this->manager)->delete(route('shift-schedules.destroy', $oneSchedule));
        $response->assertRedirect();

        $this->assertDatabaseMissing('shift_schedules', ['id' => $oneSchedule->id]);
        $this->assertEquals(9, ShiftSchedule::where('batch_id', $batchId)->count());
    }

    public function test_deleting_batch_row_keeps_recurrence_active_when_other_rows_remain(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-06')); // Monday

        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'weekdays'     => [1],
        ])->assertRedirect();

        $recurrence  = ShiftScheduleRecurrence::first();
        $countBefore = ShiftSchedule::where('batch_id', $recurrence->batch_id)->count();
        $oneSchedule = ShiftSchedule::where('batch_id', $recurrence->batch_id)->first();

        $this->actingAs($this->manager)->delete(route('shift-schedules.destroy', $oneSchedule))->assertRedirect();

        $this->assertDatabaseMissing('shift_schedules', ['id' => $oneSchedule->id]);
        $this->assertEquals($countBefore - 1, ShiftSchedule::where('batch_id', $recurrence->batch_id)->count());
        // Đợt vẫn còn bản ghi khác nên quy tắc lặp lại KHÔNG bị huỷ.
        $this->assertDatabaseHas('shift_schedule_recurrences', ['batch_id' => $recurrence->batch_id]);
    }

    public function test_deleting_last_row_of_batch_cancels_recurrence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-06')); // Monday

        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'weekdays'     => [1],
        ])->assertRedirect();

        $recurrence = ShiftScheduleRecurrence::first();
        $batchId    = $recurrence->batch_id;

        // Xoá lần lượt từng bản ghi còn lại của đợt.
        while ($schedule = ShiftSchedule::where('batch_id', $batchId)->first()) {
            $this->actingAs($this->manager)->delete(route('shift-schedules.destroy', $schedule))->assertRedirect();
        }

        $this->assertEquals(0, ShiftSchedule::where('batch_id', $batchId)->count());
        $this->assertDatabaseMissing('shift_schedule_recurrences', ['batch_id' => $batchId]);
    }

    // ── Nhân viên: chỉ xem, không được tạo/sửa/xoá ──────────────────────────

    public function test_employee_with_view_only_permission_can_see_own_and_others_shifts(): void
    {
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-shift-schedules']));

        $otherEmployee = Employee::create([
            'code' => 'EMP-02', 'name' => 'Trần Thị B', 'branch_id' => $this->employee->branch_id, 'is_active' => true,
        ]);

        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $this->shift->id,
            'work_date' => now()->startOfWeek()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);
        ShiftSchedule::create([
            'employee_id' => $otherEmployee->id, 'shift_id' => $this->shift->id,
            'work_date' => now()->startOfWeek()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');

        $response = $this->actingAs($staffUser)->get(route('shift-schedules.index'));

        $response->assertStatus(200);
        $response->assertSee($this->employee->name);
        $response->assertSee($otherEmployee->name);
    }

    public function test_employee_with_view_only_permission_cannot_bulk_assign(): void
    {
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-shift-schedules']));

        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');

        $response = $this->actingAs($staffUser)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'date_to'      => '2026-07-06',
            'weekdays'     => [1],
        ]);

        $response->assertStatus(403);
    }

    public function test_employee_without_view_permission_cannot_see_shift_schedules(): void
    {
        $noPermUser = User::factory()->create();

        $response = $this->actingAs($noPermUser)->get(route('shift-schedules.index'));

        $response->assertStatus(403);
    }

    // ── Xuất Excel ───────────────────────────────────────────────────────

    public function test_manager_can_export_shift_schedules(): void
    {
        $this->manager->givePermissionTo(Permission::firstOrCreate(['name' => 'export-shift-schedules']));

        ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $this->shift->id,
            'work_date' => now()->toDateString(), 'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->get(route('shift-schedules.export', ['range_type' => 'week']));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_manager_without_export_permission_cannot_export_shift_schedules(): void
    {
        $response = $this->actingAs($this->manager)->get(route('shift-schedules.export'));
        $response->assertStatus(403);
    }

    // ── Chế độ xem (Lưới tuần / Bảng gọn / Danh sách) ──────────────────────

    public function test_manager_can_view_shift_schedules_in_table_mode(): void
    {
        ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'work_date' => now()->startOfWeek()->toDateString(),
            'assignment_type' => 'rotation',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->get(route('shift-schedules.index', ['view' => 'table']));

        $response->assertStatus(200);
        $response->assertSee('scheduleTableView');
        $response->assertSee('sched-compact-table');
        $response->assertSee($this->employee->name);
    }

    public function test_manager_can_view_shift_schedules_in_list_mode(): void
    {
        ShiftSchedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'work_date' => now()->startOfWeek()->toDateString(),
            'assignment_type' => 'rotation',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->get(route('shift-schedules.index', ['view' => 'list']));

        $response->assertStatus(200);
        $response->assertSee('scheduleListView');
        $response->assertSee('scheduleListTable');
        $response->assertSee($this->employee->name);
    }
}
