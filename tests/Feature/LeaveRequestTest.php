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

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $staffUser;
    private Employee $staffEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        foreach (['view-leave-requests', 'create-leave-requests', 'approve-leave-requests'] as $perm) {
            $managerRole->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        foreach (['view-leave-requests', 'create-leave-requests'] as $perm) {
            $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->staffUser = User::factory()->create();
        $this->staffUser->assignRole('staff');

        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        $this->staffEmployee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'user_id' => $this->staffUser->id,
            'branch_id' => $branch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => true,
        ]);
    }

    public function test_employee_can_create_leave_request(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from' => now()->addDays(3)->toDateString(),
            'date_to'   => now()->addDays(5)->toDateString(),
            'type'      => 'annual',
            'reason'    => 'Về quê thăm gia đình',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $this->staffEmployee->id,
            'status'      => 'pending',
            'type'        => 'annual',
        ]);
    }

    public function test_manager_can_create_leave_request_for_another_employee(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');
        $otherEmployee = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'user_id' => $otherUser->id, 'is_active' => true, 'employment_type' => 'full_time', 'is_office' => true]);

        $response = $this->actingAs($this->manager)->post(route('leave-requests.store'), [
            'employee_id' => $otherEmployee->id,
            'date_from'   => now()->addDays(3)->toDateString(),
            'date_to'     => now()->addDays(5)->toDateString(),
            'type'        => 'annual',
            'reason'      => 'Quản lý tạo hộ',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $otherEmployee->id,
            'status'      => 'pending',
        ]);
    }

    public function test_manager_must_select_employee_when_creating_leave_request(): void
    {
        $response = $this->actingAs($this->manager)->post(route('leave-requests.store'), [
            'date_from' => now()->addDays(3)->toDateString(),
            'date_to'   => now()->addDays(5)->toDateString(),
            'type'      => 'annual',
            'reason'    => 'Thiếu chọn nhân viên',
        ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_regular_employee_cannot_spoof_employee_id_on_leave_request(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');
        $otherEmployee = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'user_id' => $otherUser->id, 'is_active' => true, 'employment_type' => 'full_time', 'is_office' => true]);

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'employee_id' => $otherEmployee->id,
            'date_from'   => now()->addDays(3)->toDateString(),
            'date_to'     => now()->addDays(5)->toDateString(),
            'type'        => 'annual',
            'reason'      => 'Cố tạo hộ dù không có quyền',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['employee_id' => $this->staffEmployee->id]);
        $this->assertDatabaseMissing('leave_requests', ['employee_id' => $otherEmployee->id]);
    }

    public function test_ineligible_employee_cannot_request_annual_leave(): void
    {
        $this->staffEmployee->update(['is_office' => false]);

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from' => now()->addDays(3)->toDateString(),
            'date_to'   => now()->addDays(4)->toDateString(),
            'type'      => 'annual',
            'reason'    => 'Không đủ điều kiện',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('leave_requests', ['employee_id' => $this->staffEmployee->id]);
    }

    public function test_annual_leave_request_beyond_remaining_balance_is_rejected(): void
    {
        // Nhân viên mới vào làm hôm nay — chưa tích lũy đủ tháng nào để có ngày phép năm.
        $this->staffEmployee->update(['joined_at' => now()->toDateString()]);

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from' => now()->addDays(3)->toDateString(),
            'date_to'   => now()->addDays(4)->toDateString(),
            'type'      => 'annual',
            'reason'    => 'Vượt quá số ngày phép còn lại',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('leave_requests', ['employee_id' => $this->staffEmployee->id]);
    }

    public function test_non_annual_leave_type_bypasses_eligibility_check(): void
    {
        $this->staffEmployee->update(['is_office' => false]);

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from' => now()->addDays(3)->toDateString(),
            'date_to'   => now()->addDays(4)->toDateString(),
            'type'      => 'unpaid',
            'reason'    => 'Nghỉ không lương vẫn được phép',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $this->staffEmployee->id,
            'type'        => 'unpaid',
        ]);
    }

    public function test_date_to_before_date_from_is_rejected(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from' => now()->addDays(5)->toDateString(),
            'date_to'   => now()->addDays(3)->toDateString(),
            'type'      => 'annual',
            'reason'    => 'Test',
        ]);

        $response->assertSessionHasErrors('date_to');
    }

    public function test_manager_can_approve_leave_request_and_cancels_shift_schedules_in_range(): void
    {
        $shift = Shift::create(['code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        $scheduleInRange = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => now()->addDays(4)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $scheduleOutOfRange = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => now()->addDays(10)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $leave = LeaveRequest::create([
            'code' => 'LR-TEST-0001', 'employee_id' => $this->staffEmployee->id,
            'date_from' => now()->addDays(3)->toDateString(), 'date_to' => now()->addDays(5)->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->post(route('leave-requests.approve', $leave));

        $response->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'approved']);
        $this->assertEquals('cancelled', $scheduleInRange->fresh()->status);
        $this->assertEquals('scheduled', $scheduleOutOfRange->fresh()->status);
    }

    public function test_cannot_approve_twice(): void
    {
        $leave = LeaveRequest::create([
            'code' => 'LR-TEST-0002', 'employee_id' => $this->staffEmployee->id,
            'date_from' => now()->addDay()->toDateString(), 'date_to' => now()->addDays(2)->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ', 'status' => 'approved',
            'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)->post(route('leave-requests.approve', $leave));
        $response->assertStatus(403);
    }

    public function test_reject_requires_reason(): void
    {
        $leave = LeaveRequest::create([
            'code' => 'LR-TEST-0003', 'employee_id' => $this->staffEmployee->id,
            'date_from' => now()->addDay()->toDateString(), 'date_to' => now()->addDays(2)->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->post(route('leave-requests.reject', $leave), []);
        $response->assertSessionHasErrors('rejection_reason');

        $response = $this->actingAs($this->manager)->post(route('leave-requests.reject', $leave), [
            'rejection_reason' => 'Không đủ nhân sự trong ngày này',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'rejected']);
    }

    public function test_employee_only_sees_own_requests_while_approver_sees_all(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');
        $otherEmployee = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'user_id' => $otherUser->id, 'is_active' => true, 'employment_type' => 'full_time', 'is_office' => true]);

        $mine = LeaveRequest::create([
            'code' => 'LR-A', 'employee_id' => $this->staffEmployee->id, 'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(), 'type' => 'annual', 'reason' => 'A', 'status' => 'pending',
        ]);
        $others = LeaveRequest::create([
            'code' => 'LR-B', 'employee_id' => $otherEmployee->id, 'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(), 'type' => 'annual', 'reason' => 'B', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staffUser)->get(route('leave-requests.index'));
        $response->assertSee('LR-A')->assertDontSee('LR-B');

        $response = $this->actingAs($this->manager)->get(route('leave-requests.index'));
        $response->assertSee('LR-A')->assertSee('LR-B');
    }

    public function test_employee_without_permission_cannot_approve(): void
    {
        $leave = LeaveRequest::create([
            'code' => 'LR-TEST-0004', 'employee_id' => $this->staffEmployee->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.approve', $leave));
        $response->assertStatus(403);
    }

    public function test_guest_redirected_to_login(): void
    {
        $response = $this->get(route('leave-requests.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_owner_can_cancel_own_pending_request(): void
    {
        $leave = LeaveRequest::create([
            'code' => 'LR-TEST-0005', 'employee_id' => $this->staffEmployee->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staffUser)->delete(route('leave-requests.destroy', $leave));
        $response->assertRedirect();
        $this->assertSoftDeleted('leave_requests', ['id' => $leave->id]);
    }
}
