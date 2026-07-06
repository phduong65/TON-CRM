<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftScheduleRecurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'manager']);
        foreach (['view-shifts', 'create-shifts', 'edit-shifts', 'delete-shifts'] as $perm) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
    }

    public function test_manager_can_view_shift_list(): void
    {
        $response = $this->actingAs($this->manager)->get(route('shifts.index'));
        $response->assertStatus(200);
    }

    public function test_manager_can_create_shift(): void
    {
        $response = $this->actingAs($this->manager)->post(route('shifts.store'), [
            'code'       => 'CA-HC',
            'name'       => 'Ca hành chính',
            'start_time' => '08:00',
            'end_time'   => '17:00',
            'shift_type' => 'fulltime',
            'work_mode'  => 'onsite',
        ]);

        $response->assertRedirect(route('shifts.index'));
        $this->assertDatabaseHas('shifts', ['code' => 'CA-HC', 'name' => 'Ca hành chính']);
    }

    public function test_manager_can_update_shift(): void
    {
        $shift = Shift::create([
            'code' => 'CA-1', 'name' => 'Ca 1', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);

        $response = $this->actingAs($this->manager)->put(route('shifts.update', $shift), [
            'code'       => 'CA-1',
            'name'       => 'Ca 1 (sửa)',
            'start_time' => '08:30',
            'end_time'   => '17:30',
            'shift_type' => 'fulltime',
            'work_mode'  => 'wfh',
        ]);

        $response->assertRedirect(route('shifts.index'));
        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'name' => 'Ca 1 (sửa)', 'work_mode' => 'wfh']);
    }

    public function test_manager_can_delete_shift(): void
    {
        $shift = Shift::create([
            'code' => 'CA-2', 'name' => 'Ca 2', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shifts.destroy', $shift));

        $response->assertRedirect(route('shifts.index'));
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_deleting_shift_removes_attendance_logs_for_its_schedules(): void
    {
        $branch = \App\Models\Branch::create(['code' => 'BR-10', 'name' => 'Chi nhánh 10', 'is_active' => true]);
        $shift  = Shift::create([
            'code' => 'CA-6', 'name' => 'Ca 6', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);
        $employee = Employee::create(['code' => 'EMP-12', 'name' => 'NV 12', 'branch_id' => $branch->id, 'is_active' => true]);

        $schedule = ShiftSchedule::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id, 'work_date' => '2026-07-06',
            'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'shift_schedule_id' => $schedule->id,
            'work_date' => '2026-07-06', 'check_in_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shifts.destroy', $shift));

        $response->assertRedirect(route('shifts.index'));
        $this->assertDatabaseMissing('attendance_logs', ['id' => $log->id]);
        $this->assertDatabaseMissing('shift_schedules', ['id' => $schedule->id]);
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_deleting_shift_removes_all_schedules_for_every_employee_past_and_future(): void
    {
        $branch = \App\Models\Branch::create(['code' => 'BR-9', 'name' => 'Chi nhánh 9', 'is_active' => true]);
        $shift  = Shift::create([
            'code' => 'CA-3', 'name' => 'Ca 3', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);
        $emp1 = Employee::create(['code' => 'EMP-10', 'name' => 'NV 10', 'branch_id' => $branch->id, 'is_active' => true]);
        $emp2 = Employee::create(['code' => 'EMP-11', 'name' => 'NV 11', 'branch_id' => $branch->id, 'is_active' => true]);

        $past = ShiftSchedule::create([
            'employee_id' => $emp1->id, 'shift_id' => $shift->id, 'work_date' => '2026-01-01',
            'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);
        $future = ShiftSchedule::create([
            'employee_id' => $emp2->id, 'shift_id' => $shift->id, 'work_date' => '2027-01-01',
            'assignment_type' => 'rotation', 'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shifts.destroy', $shift));

        $response->assertRedirect(route('shifts.index'));
        $this->assertDatabaseMissing('shift_schedules', ['id' => $past->id]);
        $this->assertDatabaseMissing('shift_schedules', ['id' => $future->id]);
    }

    public function test_deleting_shift_removes_recurrence_when_it_is_the_only_shift_in_the_batch(): void
    {
        $shift = Shift::create([
            'code' => 'CA-4', 'name' => 'Ca 4', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);

        $recurrence = ShiftScheduleRecurrence::create([
            'batch_id' => (string) \Illuminate\Support\Str::uuid(),
            'shift_ids' => [$shift->id],
            'employee_ids' => [],
            'weekdays' => [1],
            'starts_on' => '2026-07-06',
            'is_active' => true,
        ]);

        $this->actingAs($this->manager)->delete(route('shifts.destroy', $shift))->assertRedirect();

        $this->assertDatabaseMissing('shift_schedule_recurrences', ['id' => $recurrence->id]);
    }

    public function test_deleting_shift_only_removes_it_from_recurrence_shift_ids_when_others_remain(): void
    {
        $shiftA = Shift::create([
            'code' => 'CA-5A', 'name' => 'Ca 5A', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);
        $shiftB = Shift::create([
            'code' => 'CA-5B', 'name' => 'Ca 5B', 'start_time' => '13:00', 'end_time' => '22:00', 'work_mode' => 'onsite',
        ]);

        $recurrence = ShiftScheduleRecurrence::create([
            'batch_id' => (string) \Illuminate\Support\Str::uuid(),
            'shift_ids' => [$shiftA->id, $shiftB->id],
            'employee_ids' => [],
            'weekdays' => [1],
            'starts_on' => '2026-07-06',
            'is_active' => true,
        ]);

        $this->actingAs($this->manager)->delete(route('shifts.destroy', $shiftA))->assertRedirect();

        $this->assertDatabaseHas('shift_schedule_recurrences', ['id' => $recurrence->id]);
        $this->assertEquals([$shiftB->id], $recurrence->fresh()->shift_ids);
    }

    public function test_user_without_permission_cannot_view_shifts(): void
    {
        $noPermUser = User::factory()->create();
        $response = $this->actingAs($noPermUser)->get(route('shifts.index'));
        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('shifts.index'));
        $response->assertRedirect(route('login'));
    }
}
