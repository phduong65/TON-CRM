<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\StaffRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffRequestTest extends TestCase
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
        foreach (['view-staff-requests', 'create-staff-requests', 'approve-staff-requests', 'view-leave-requests', 'view-shift-swaps'] as $perm) {
            $managerRole->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        foreach (['view-staff-requests', 'create-staff-requests'] as $perm) {
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
        ]);
    }

    public function test_employee_can_create_attendance_correction_request(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'         => 'attendance_correction',
            'work_date'    => now()->toDateString(),
            'check_in_at'  => '08:05',
            'reason'       => 'Quên chấm công buổi sáng',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', [
            'employee_id' => $this->staffEmployee->id,
            'type'        => 'attendance_correction',
            'status'      => 'pending',
        ]);
        $this->assertEquals(['check_in_at' => '08:05'], StaffRequest::first()->payload);
    }

    public function test_approver_can_create_staff_request_for_another_employee(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');
        $otherEmployee = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'user_id' => $otherUser->id, 'is_active' => true]);

        $response = $this->actingAs($this->manager)->post(route('staff-requests.store'), [
            'employee_id'  => $otherEmployee->id,
            'type'         => 'attendance_correction',
            'work_date'    => now()->toDateString(),
            'check_in_at'  => '08:05',
            'reason'       => 'Quản lý tạo hộ',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', [
            'employee_id' => $otherEmployee->id,
            'type'        => 'attendance_correction',
            'status'      => 'pending',
        ]);
    }

    public function test_approver_must_select_employee_when_creating_request(): void
    {
        $response = $this->actingAs($this->manager)->post(route('staff-requests.store'), [
            'type'         => 'attendance_correction',
            'work_date'    => now()->toDateString(),
            'check_in_at'  => '08:05',
            'reason'       => 'Thiếu chọn nhân viên',
        ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_regular_employee_cannot_spoof_employee_id(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');
        $otherEmployee = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'user_id' => $otherUser->id, 'is_active' => true]);

        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'employee_id'  => $otherEmployee->id,
            'type'         => 'attendance_correction',
            'work_date'    => now()->toDateString(),
            'check_in_at'  => '08:05',
            'reason'       => 'Cố tạo hộ dù không có quyền',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', [
            'employee_id' => $this->staffEmployee->id, // vẫn là chính mình, không phải otherEmployee
            'type'        => 'attendance_correction',
        ]);
        $this->assertDatabaseMissing('staff_requests', ['employee_id' => $otherEmployee->id]);
    }

    public function test_attendance_correction_requires_at_least_one_time_field(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'      => 'attendance_correction',
            'work_date' => now()->toDateString(),
            'reason'    => 'Thiếu chấm công',
        ]);

        $response->assertSessionHasErrors('check_in_at');
    }

    public function test_employee_can_create_business_trip_request(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'      => 'business_trip',
            'work_date' => now()->toDateString(),
            'from_time' => '09:00',
            'to_time'   => '11:00',
            'location'  => 'Gặp khách quận 1',
            'reason'    => 'Gặp đối tác',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', ['type' => 'business_trip', 'status' => 'pending']);
    }

    public function test_business_trip_to_time_must_be_after_from_time(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'      => 'business_trip',
            'work_date' => now()->toDateString(),
            'from_time' => '11:00',
            'to_time'   => '09:00',
            'location'  => 'Gặp khách quận 1',
            'reason'    => 'Gặp đối tác',
        ]);

        $response->assertSessionHasErrors('to_time');
    }

    public function test_employee_can_create_late_early_request(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'      => 'late_early',
            'work_date' => now()->toDateString(),
            'mode'      => 'late',
            'minutes'   => 30,
            'reason'    => 'Kẹt xe',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', ['type' => 'late_early', 'status' => 'pending']);
    }

    public function test_employee_can_create_time_change_request(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'          => 'time_change',
            'work_date'     => now()->toDateString(),
            'new_check_in'  => '10:00',
            'new_check_out' => '19:00',
            'reason'        => 'Đưa con đi học',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', ['type' => 'time_change', 'status' => 'pending']);
    }

    public function test_manager_approving_attendance_correction_updates_attendance_log_with_late_minutes(): void
    {
        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00',
            'work_mode' => 'onsite', 'grace_late_minutes' => 5,
        ]);
        $workDate = now()->toDateString();
        ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $staffRequest = StaffRequest::create([
            'code' => 'ATC-TEST-0001', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => $workDate,
            'payload' => ['check_in_at' => '08:20'], 'reason' => 'Quên chấm công', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest));

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', ['id' => $staffRequest->id, 'status' => 'approved']);

        $log = AttendanceLog::where('employee_id', $this->staffEmployee->id)->where('work_date', $workDate)->first();
        $this->assertNotNull($log);
        $this->assertEquals('08:20:00', $log->check_in_at->format('H:i:s'));
        $this->assertEquals('manual', $log->check_in_method);
        $this->assertEquals(15, $log->late_minutes); // 20 phút trễ - 5 phút cho phép
    }

    public function test_manager_approving_attendance_correction_without_schedule_has_zero_late_minutes(): void
    {
        $workDate = now()->toDateString();
        $staffRequest = StaffRequest::create([
            'code' => 'ATC-TEST-0002', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => $workDate,
            'payload' => ['check_out_at' => '17:30'], 'reason' => 'Quên check-out', 'status' => 'pending',
        ]);

        $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest));

        $log = AttendanceLog::where('employee_id', $this->staffEmployee->id)->where('work_date', $workDate)->first();
        $this->assertNotNull($log);
        $this->assertEquals('17:30:00', $log->check_out_at->format('H:i:s'));
        $this->assertEquals(0, $log->early_minutes);
    }

    public function test_cannot_approve_twice(): void
    {
        $staffRequest = StaffRequest::create([
            'code' => 'BTR-TEST-0001', 'employee_id' => $this->staffEmployee->id,
            'type' => 'business_trip', 'work_date' => now()->toDateString(),
            'payload' => ['from_time' => '09:00', 'to_time' => '11:00', 'location' => 'X'],
            'reason' => 'X', 'status' => 'approved', 'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest));
        $response->assertStatus(403);
    }

    public function test_reject_requires_reason(): void
    {
        $staffRequest = StaffRequest::create([
            'code' => 'LE-TEST-0001', 'employee_id' => $this->staffEmployee->id,
            'type' => 'late_early', 'work_date' => now()->toDateString(),
            'payload' => ['mode' => 'late', 'minutes' => 15], 'reason' => 'Kẹt xe', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->post(route('staff-requests.reject', $staffRequest), []);
        $response->assertSessionHasErrors('rejection_reason');

        $response = $this->actingAs($this->manager)->post(route('staff-requests.reject', $staffRequest), [
            'rejection_reason' => 'Không hợp lệ',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', ['id' => $staffRequest->id, 'status' => 'rejected']);
    }

    public function test_employee_without_permission_cannot_approve(): void
    {
        $staffRequest = StaffRequest::create([
            'code' => 'TC-TEST-0001', 'employee_id' => $this->staffEmployee->id,
            'type' => 'time_change', 'work_date' => now()->toDateString(),
            'payload' => ['new_check_in' => '10:00', 'new_check_out' => '19:00'],
            'reason' => 'X', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.approve', $staffRequest));
        $response->assertStatus(403);
    }

    public function test_guest_redirected_to_login(): void
    {
        $response = $this->get(route('staff-requests.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_owner_can_cancel_own_pending_request(): void
    {
        $staffRequest = StaffRequest::create([
            'code' => 'ATC-TEST-0003', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'payload' => ['check_in_at' => '08:05'], 'reason' => 'X', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staffUser)->delete(route('staff-requests.destroy', $staffRequest));
        $response->assertRedirect();
        $this->assertSoftDeleted('staff_requests', ['id' => $staffRequest->id]);
    }

    public function test_hub_index_merges_leave_swap_and_staff_request_types(): void
    {
        LeaveRequest::create([
            'code' => 'LR-HUB-0001', 'employee_id' => $this->staffEmployee->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ', 'status' => 'pending',
        ]);
        StaffRequest::create([
            'code' => 'BTR-HUB-0001', 'employee_id' => $this->staffEmployee->id,
            'type' => 'business_trip', 'work_date' => now()->toDateString(),
            'payload' => ['from_time' => '09:00', 'to_time' => '11:00', 'location' => 'X'],
            'reason' => 'X', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->get(route('staff-requests.index'));
        $response->assertSee('LR-HUB-0001')->assertSee('BTR-HUB-0001');
    }

    public function test_employee_only_sees_own_requests_while_approver_sees_all(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');
        $otherEmployee = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'user_id' => $otherUser->id, 'is_active' => true]);

        StaffRequest::create([
            'code' => 'BTR-A', 'employee_id' => $this->staffEmployee->id,
            'type' => 'business_trip', 'work_date' => now()->toDateString(),
            'payload' => ['from_time' => '09:00', 'to_time' => '11:00', 'location' => 'X'],
            'reason' => 'A', 'status' => 'pending',
        ]);
        StaffRequest::create([
            'code' => 'BTR-B', 'employee_id' => $otherEmployee->id,
            'type' => 'business_trip', 'work_date' => now()->toDateString(),
            'payload' => ['from_time' => '09:00', 'to_time' => '11:00', 'location' => 'Y'],
            'reason' => 'B', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staffUser)->get(route('staff-requests.index'));
        $response->assertSee('BTR-A')->assertDontSee('BTR-B');

        $response = $this->actingAs($this->manager)->get(route('staff-requests.index'));
        $response->assertSee('BTR-A')->assertSee('BTR-B');
    }

    public function test_branch_filter_narrows_hub_results(): void
    {
        $otherBranch = Branch::create(['code' => 'BR-2', 'name' => 'Chi nhánh 2', 'is_active' => true]);
        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');
        $otherEmployee = Employee::create([
            'code' => 'EMP-02', 'name' => 'Trần Thị B', 'user_id' => $otherUser->id,
            'branch_id' => $otherBranch->id, 'is_active' => true,
        ]);

        StaffRequest::create([
            'code' => 'BTR-BR1', 'employee_id' => $this->staffEmployee->id,
            'type' => 'business_trip', 'work_date' => now()->toDateString(),
            'payload' => ['from_time' => '09:00', 'to_time' => '11:00', 'location' => 'X'],
            'reason' => 'A', 'status' => 'pending',
        ]);
        StaffRequest::create([
            'code' => 'BTR-BR2', 'employee_id' => $otherEmployee->id,
            'type' => 'business_trip', 'work_date' => now()->toDateString(),
            'payload' => ['from_time' => '09:00', 'to_time' => '11:00', 'location' => 'Y'],
            'reason' => 'B', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->get(route('staff-requests.index', ['branch_id' => $otherBranch->id]));
        file_put_contents(sys_get_temp_dir() . '/staff_request_debug.html', $response->content());
        $response->assertDontSee('BTR-BR1')->assertSee('BTR-BR2');
    }

    public function test_team_filter_narrows_hub_results(): void
    {
        $team = Team::create(['code' => 'TEAM-BAR', 'name' => 'Đội Bar', 'is_active' => true]);
        $this->staffEmployee->update(['team_id' => $team->id]);

        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');
        $otherEmployee = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'user_id' => $otherUser->id, 'is_active' => true]);

        StaffRequest::create([
            'code' => 'BTR-T1', 'employee_id' => $this->staffEmployee->id,
            'type' => 'business_trip', 'work_date' => now()->toDateString(),
            'payload' => ['from_time' => '09:00', 'to_time' => '11:00', 'location' => 'X'],
            'reason' => 'A', 'status' => 'pending',
        ]);
        StaffRequest::create([
            'code' => 'BTR-T2', 'employee_id' => $otherEmployee->id,
            'type' => 'business_trip', 'work_date' => now()->toDateString(),
            'payload' => ['from_time' => '09:00', 'to_time' => '11:00', 'location' => 'Y'],
            'reason' => 'B', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->get(route('staff-requests.index', ['team_id' => $team->id]));
        $response->assertSee('BTR-T1')->assertDontSee('BTR-T2');
    }
}
