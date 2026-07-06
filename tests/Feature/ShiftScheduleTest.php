<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftScheduleRecurrence;
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
            'custom_start_time' => '12:00:00',
            'custom_end_time'   => '20:00:00',
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
            'custom_start_time' => '12:00:00', 'custom_end_time' => '20:00:00',
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

    public function test_deleting_one_row_of_bulk_batch_deletes_entire_batch_for_all_employees_and_dates(): void
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

        $this->assertEquals(0, ShiftSchedule::where('batch_id', $batchId)->count());
    }

    public function test_deleting_batch_row_removes_recurrence_so_no_more_rows_generate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-06')); // Monday

        $this->actingAs($this->manager)->post(route('shift-schedules.bulk-store'), [
            'employee_ids' => [$this->employee->id],
            'shift_ids'    => [$this->shift->id],
            'date_from'    => '2026-07-06',
            'weekdays'     => [1],
        ])->assertRedirect();

        $recurrence  = ShiftScheduleRecurrence::first();
        $oneSchedule = ShiftSchedule::where('batch_id', $recurrence->batch_id)->first();

        $this->actingAs($this->manager)->delete(route('shift-schedules.destroy', $oneSchedule))->assertRedirect();

        $this->assertDatabaseMissing('shift_schedule_recurrences', ['batch_id' => $recurrence->batch_id]);

        Carbon::setTestNow(Carbon::parse('2026-07-06')->addWeeks(4));
        $this->artisan('shift-schedules:generate-recurring')->assertExitCode(0);

        $this->assertEquals(0, ShiftSchedule::where('batch_id', $recurrence->batch_id)->count());
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
}
