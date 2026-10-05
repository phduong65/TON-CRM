<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Modal "Chi tiết yêu cầu" (StaffRequestsController::details) phải trả đúng các ca nhân viên
 * đã chọn khi xin "Nghỉ N ca cụ thể".
 */
class StaffRequestLeaveShiftsDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => 'manager']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'approve-leave-requests']));

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        $this->employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Võ Nguyên Đoan Triều',
            'branch_id' => $branch->id, 'is_active' => true,
        ]);
    }

    private function schedule(string $name, string $start, string $end, string $date): ShiftSchedule
    {
        $shift = Shift::create([
            'code' => strtoupper(substr(md5($name), 0, 6)), 'name' => $name,
            'start_time' => $start, 'end_time' => $end, 'work_mode' => 'onsite',
        ]);

        return ShiftSchedule::create([
            'employee_id' => $this->employee->id, 'shift_id' => $shift->id,
            'work_date' => $date, 'assignment_type' => 'fixed', 'status' => 'scheduled',
        ]);
    }

    private function leave(array $overrides = []): LeaveRequest
    {
        return LeaveRequest::create(array_merge([
            'code' => 'LR-TEST-01', 'employee_id' => $this->employee->id,
            'date_from' => '2026-10-06', 'date_to' => '2026-10-06',
            'type' => 'unpaid', 'reason' => 'Ngày off trong tuần', 'status' => 'pending',
            'is_partial_day' => true, 'day_fraction' => 0.47,
        ], $overrides));
    }

    public function test_details_lists_selected_shifts_for_partial_leave(): void
    {
        $sang = $this->schedule('Ca Bar Sáng', '11:00', '15:00', '2026-10-06');
        $toi  = $this->schedule('Ca Bar Tối', '18:00', '23:00', '2026-10-06');
        $this->schedule('Ca Bar Fulltime', '17:00', '01:00', '2026-10-06'); // không chọn

        $leave = $this->leave();
        $leave->shiftSchedules()->attach([
            $toi->id  => ['day_fraction' => 0.25],
            $sang->id => ['day_fraction' => 0.22],
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson(route('staff-requests.details', ['type' => 'leave', 'id' => $leave->id]));

        $response->assertOk();
        $shifts = $response->json('leave_shifts');
        $this->assertCount(2, $shifts);
        // Sắp xếp theo giờ bắt đầu: Sáng trước Tối; không lẫn ca không được chọn.
        $this->assertSame('Ca Bar Sáng', $shifts[0]['name']);
        $this->assertSame('11:00 – 15:00', $shifts[0]['time']);
        $this->assertSame('06/10/2026', $shifts[0]['date']);
        $this->assertSame('Ca Bar Tối', $shifts[1]['name']);
        $this->assertSame('18:00 – 23:00', $shifts[1]['time']);
    }

    public function test_details_falls_back_to_legacy_single_shift_schedule_id(): void
    {
        $sang = $this->schedule('Ca Bar Sáng', '11:00', '15:00', '2026-10-06');
        $leave = $this->leave(['shift_schedule_id' => $sang->id]);

        $response = $this->actingAs($this->manager)
            ->getJson(route('staff-requests.details', ['type' => 'leave', 'id' => $leave->id]));

        $response->assertOk();
        $this->assertCount(1, $response->json('leave_shifts'));
        $this->assertSame('Ca Bar Sáng', $response->json('leave_shifts.0.name'));
    }

    public function test_details_returns_no_shifts_for_full_day_leave(): void
    {
        $leave = $this->leave(['is_partial_day' => false, 'day_fraction' => null]);

        $response = $this->actingAs($this->manager)
            ->getJson(route('staff-requests.details', ['type' => 'leave', 'id' => $leave->id]));

        $response->assertOk();
        $this->assertSame([], $response->json('leave_shifts'));
    }

    public function test_leave_requests_index_shows_selected_shift_names(): void
    {
        $this->manager->givePermissionTo(Permission::firstOrCreate(['name' => 'view-leave-requests']));
        $toi = $this->schedule('Ca Bar Tối', '18:00', '23:00', '2026-10-06');
        $leave = $this->leave();
        $leave->shiftSchedules()->attach([$toi->id => ['day_fraction' => 0.47]]);

        $this->actingAs($this->manager)
            ->get(route('leave-requests.index'))
            ->assertOk()
            ->assertSee('Ca Bar Tối')
            ->assertSee('18:00 – 23:00');
    }
}
