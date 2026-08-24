<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use App\Services\AnnualLeaveService;
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

        $response->assertSessionHasErrors('type');
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

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseMissing('leave_requests', ['employee_id' => $this->staffEmployee->id]);
    }

    /**
     * remainingDays() mặc định tính theo NĂM HIỆN TẠI — nếu store() không truyền rõ năm của
     * date_from, xin nghỉ phép năm cho ngày thuộc NĂM SAU (chưa tích luỹ ngày nào, vì năm đó
     * chưa bắt đầu) sẽ bị kiểm tra nhầm với số dư của năm hiện tại (có thể còn dư nhiều do nhân
     * viên lâu năm chưa dùng ngày phép nào) và được duyệt sai. Xem LeaveRequestsController::store().
     */
    public function test_annual_leave_for_future_year_is_checked_against_that_years_balance(): void
    {
        // Nhân viên lâu năm — dư nhiều ngày phép NĂM NAY (chưa dùng ngày nào).
        $this->staffEmployee->update(['joined_at' => now()->subYears(2)->toDateString()]);

        $nextYearDate = now()->addYear()->startOfYear()->addDays(5);

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from' => $nextYearDate->toDateString(),
            'date_to'   => $nextYearDate->copy()->addDays(2)->toDateString(),
            'type'      => 'annual',
            'reason'    => 'Xin nghỉ đầu năm sau',
        ]);

        $response->assertSessionHasErrors('type');
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

    /**
     * "Nghỉ ốm" (sick) và "Khác" (other) đã bị bỏ khỏi hệ thống — chỉ còn "annual" (có lương)
     * và "unpaid" (không lương). Xem LeaveRequestsController::store().
     */
    public function test_sick_and_other_leave_types_are_no_longer_accepted(): void
    {
        foreach (['sick', 'other'] as $type) {
            $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
                'date_from' => now()->addDays(3)->toDateString(),
                'date_to'   => now()->addDays(4)->toDateString(),
                'type'      => $type,
                'reason'    => 'x',
            ]);

            $response->assertSessionHasErrors('type');
        }

        $this->assertDatabaseCount('leave_requests', 0);
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

    public function test_owner_cannot_cancel_approved_request_without_delete_permission(): void
    {
        $leave = LeaveRequest::create([
            'code' => 'LR-TEST-0006', 'employee_id' => $this->staffEmployee->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ', 'status' => 'approved',
            'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->staffUser)->delete(route('leave-requests.destroy', $leave));

        $response->assertStatus(403);
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'deleted_at' => null]);
    }

    public function test_manager_with_delete_permission_can_purge_approved_request(): void
    {
        $this->manager->givePermissionTo(Permission::firstOrCreate(['name' => 'delete-leave-requests']));

        $leave = LeaveRequest::create([
            'code' => 'LR-TEST-0007', 'employee_id' => $this->staffEmployee->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ', 'status' => 'approved',
            'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)->delete(route('leave-requests.destroy', $leave));

        $response->assertRedirect();
        $this->assertSoftDeleted('leave_requests', ['id' => $leave->id]);
    }

    public function test_manager_without_delete_permission_cannot_purge_pending_request_of_others(): void
    {
        $leave = LeaveRequest::create([
            'code' => 'LR-TEST-0008', 'employee_id' => $this->staffEmployee->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('leave-requests.destroy', $leave));

        $response->assertStatus(403);
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'deleted_at' => null]);
    }

    private function makeMorningShiftSchedule(): ShiftSchedule
    {
        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính',
            'start_time' => '09:00', 'end_time' => '18:00', 'break_minutes' => 60, 'work_mode' => 'onsite',
        ]);

        return ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => now()->addDays(3)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
    }

    /**
     * NV part-time/đa ca có 2 ca trong cùng 1 ngày (VD sáng + chiều) — helper dùng chung cho các
     * test chọn nhiều ca cụ thể.
     */
    private function makeTwoShiftsSameDay(): array
    {
        $morningShift = Shift::create([
            'code' => 'CA-PT-S', 'name' => 'Ca sáng part-time', 'shift_type' => 'parttime',
            'start_time' => '08:00', 'end_time' => '12:00', 'break_minutes' => 0, 'work_mode' => 'onsite',
        ]);
        $afternoonShift = Shift::create([
            'code' => 'CA-PT-C', 'name' => 'Ca chiều part-time', 'shift_type' => 'parttime',
            'start_time' => '13:00', 'end_time' => '17:00', 'break_minutes' => 0, 'work_mode' => 'onsite',
        ]);
        $workDate = now()->addDays(3)->toDateString();

        $morning = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $morningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $afternoon = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $afternoonShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        return [$morning, $afternoon];
    }

    /**
     * 2 ngày liên tiếp, mỗi ngày 2 ca (sáng/chiều) part-time = 4 ca tổng cộng — dùng để test kịch
     * bản chọn nhiều ca cụ thể trải dài nhiều ngày (VD nghỉ 3 trong 4 ca, đi làm ca còn lại).
     */
    private function makeFourShiftsAcrossTwoDays(): array
    {
        [$day1Morning, $day1Afternoon] = $this->makeTwoShiftsSameDay();

        $morningShift   = $day1Morning->shift;
        $afternoonShift = $day1Afternoon->shift;
        $day2 = $day1Morning->work_date->copy()->addDay()->toDateString();

        $day2Morning = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $morningShift->id,
            'work_date' => $day2, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $day2Afternoon = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $afternoonShift->id,
            'work_date' => $day2, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        return [$day1Morning, $day1Afternoon, $day2Morning, $day2Afternoon];
    }

    public function test_partial_day_leave_requires_at_least_one_shift(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from'      => now()->addDays(3)->toDateString(),
            'date_to'        => now()->addDays(3)->toDateString(),
            'type'           => 'annual',
            'reason'         => 'Nghỉ 1 số ca',
            'is_partial_day' => 1,
        ]);

        $response->assertSessionHasErrors('shift_schedule_ids');
        $this->assertDatabaseMissing('leave_requests', ['employee_id' => $this->staffEmployee->id]);
    }

    public function test_partial_day_leave_rejects_shift_outside_selected_date_range(): void
    {
        $schedule = $this->makeMorningShiftSchedule(); // work_date = hôm nay + 3 ngày

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from'          => now()->addDays(10)->toDateString(),
            'date_to'            => now()->addDays(10)->toDateString(),
            'type'               => 'annual',
            'reason'             => 'Nghỉ sai khoảng ngày',
            'is_partial_day'     => 1,
            'shift_schedule_ids' => [$schedule->id],
        ]);

        $response->assertSessionHasErrors('shift_schedule_ids');
        $this->assertDatabaseMissing('leave_requests', ['employee_id' => $this->staffEmployee->id]);
    }

    public function test_partial_day_leave_rejects_shift_belonging_to_another_employee(): void
    {
        $otherEmployee = Employee::create([
            'code' => 'EMP-02', 'name' => 'Người khác', 'branch_id' => $this->staffEmployee->branch_id,
            'is_active' => true, 'employment_type' => 'full_time', 'is_office' => true,
        ]);
        $shift = Shift::create(['code' => 'CA-OTHER', 'name' => 'Ca người khác', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        $otherSchedule = ShiftSchedule::create([
            'employee_id' => $otherEmployee->id, 'shift_id' => $shift->id,
            'work_date' => now()->addDays(3)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from'          => now()->addDays(3)->toDateString(),
            'date_to'            => now()->addDays(3)->toDateString(),
            'type'               => 'annual',
            'reason'             => 'Nghỉ ca người khác',
            'is_partial_day'     => 1,
            'shift_schedule_ids' => [$otherSchedule->id],
        ]);

        $response->assertSessionHasErrors('shift_schedule_ids');
        $this->assertDatabaseMissing('leave_requests', ['employee_id' => $this->staffEmployee->id]);
    }

    public function test_selecting_the_only_shift_of_the_day_counts_as_full_day_fraction(): void
    {
        $schedule = $this->makeMorningShiftSchedule(); // Ngày đó chỉ có đúng 1 ca

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from'          => $schedule->work_date->toDateString(),
            'date_to'            => $schedule->work_date->toDateString(),
            'type'               => 'annual',
            'reason'             => 'Nghỉ nguyên ca duy nhất trong ngày',
            'is_partial_day'     => 1,
            'shift_schedule_ids' => [$schedule->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $leave = LeaveRequest::where('reason', 'Nghỉ nguyên ca duy nhất trong ngày')->firstOrFail();
        $this->assertEquals(1.0, (float) $leave->day_fraction);
        $this->assertTrue($leave->shiftSchedules->pluck('id')->contains($schedule->id));
    }

    public function test_approving_single_shift_leave_cancels_that_shift(): void
    {
        $schedule = $this->makeMorningShiftSchedule();

        $leave = LeaveRequest::create([
            'code' => 'LR-PARTIAL-01', 'employee_id' => $this->staffEmployee->id,
            'date_from' => $schedule->work_date->toDateString(), 'date_to' => $schedule->work_date->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ', 'status' => 'pending',
            'is_partial_day' => true, 'day_fraction' => 1.0,
        ]);
        $leave->shiftSchedules()->attach($schedule->id, ['day_fraction' => 1.0]);

        $response = $this->actingAs($this->manager)->post(route('leave-requests.approve', $leave));

        $response->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'approved']);
        $schedule->refresh();
        $this->assertEquals('cancelled', $schedule->status);
    }

    /**
     * Kịch bản chính của tính năng chọn nhiều ca: NV part-time có 2 ngày, mỗi ngày 2 ca (4 ca
     * tổng cộng), chỉ muốn nghỉ 3 ca — ca còn lại (chiều ngày 2) vẫn đi làm bình thường.
     */
    public function test_can_select_specific_shifts_across_multiple_days(): void
    {
        [$day1Morning, $day1Afternoon, $day2Morning, $day2Afternoon] = $this->makeFourShiftsAcrossTwoDays();

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from'          => $day1Morning->work_date->toDateString(),
            'date_to'            => $day2Morning->work_date->toDateString(),
            'type'               => 'annual',
            'reason'             => 'Nghỉ 3 trong 4 ca',
            'is_partial_day'     => 1,
            'shift_schedule_ids' => [$day1Morning->id, $day1Afternoon->id, $day2Morning->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $leave = LeaveRequest::where('reason', 'Nghỉ 3 trong 4 ca')->firstOrFail();
        // Ngày 1: cả 2 ca đều nghỉ => fraction 1.0. Ngày 2: nghỉ 1/2 ca => fraction 0.5. Tổng 1.5.
        $this->assertEquals(1.5, (float) $leave->day_fraction);

        $selectedIds = $leave->shiftSchedules->pluck('id')->sort()->values()->all();
        $this->assertEqualsCanonicalizing([$day1Morning->id, $day1Afternoon->id, $day2Morning->id], $selectedIds);
        $this->assertFalse($leave->shiftSchedules->pluck('id')->contains($day2Afternoon->id));
    }

    public function test_approving_multi_day_selection_only_cancels_selected_shifts(): void
    {
        [$day1Morning, $day1Afternoon, $day2Morning, $day2Afternoon] = $this->makeFourShiftsAcrossTwoDays();

        $leave = LeaveRequest::create([
            'code' => 'LR-MULTI-01', 'employee_id' => $this->staffEmployee->id,
            'date_from' => $day1Morning->work_date->toDateString(), 'date_to' => $day2Morning->work_date->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ 3 trong 4 ca', 'status' => 'pending',
            'is_partial_day' => true, 'day_fraction' => 1.5,
        ]);
        $leave->shiftSchedules()->attach([
            $day1Morning->id   => ['day_fraction' => 0.5],
            $day1Afternoon->id => ['day_fraction' => 0.5],
            $day2Morning->id   => ['day_fraction' => 0.5],
        ]);

        $response = $this->actingAs($this->manager)->post(route('leave-requests.approve', $leave));
        $response->assertRedirect();

        $day1Morning->refresh();
        $day1Afternoon->refresh();
        $day2Morning->refresh();
        $day2Afternoon->refresh();

        $this->assertEquals('cancelled', $day1Morning->status);
        $this->assertEquals('cancelled', $day1Afternoon->status);
        $this->assertEquals('cancelled', $day2Morning->status);
        // Ca không được chọn (chiều ngày 2) vẫn giữ nguyên trạng thái, nhân viên vẫn đi làm ca này.
        $this->assertEquals('scheduled', $day2Afternoon->status);
    }

    public function test_partial_day_leave_deducts_fractional_annual_leave_balance(): void
    {
        [$morning, $afternoon] = $this->makeTwoShiftsSameDay();

        // Nhân viên có đúng 1.0 ngày phép năm (1 tháng thâm niên), đã dùng 0.7 ngày qua 1 đơn
        // khác đã duyệt trước đó -> chỉ còn 0.3 ngày. Xin nghỉ 1 ca (0.5 ngày, 2 ca bằng nhau
        // trong ngày) phải bị từ chối vì 0.5 > 0.3 còn lại.
        $this->staffEmployee->update(['joined_at' => now()->subMonths(1)->toDateString()]);
        LeaveRequest::create([
            'code' => 'LR-PRIOR-01', 'employee_id' => $this->staffEmployee->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Đã dùng trước', 'status' => 'approved',
            'is_partial_day' => true, 'day_fraction' => 0.7,
        ]);

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from'          => $morning->work_date->toDateString(),
            'date_to'            => $morning->work_date->toDateString(),
            'type'               => 'annual',
            'reason'             => 'Nghỉ ca sáng',
            'is_partial_day'     => 1,
            'shift_schedule_ids' => [$morning->id],
        ]);

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseMissing('leave_requests', ['reason' => 'Nghỉ ca sáng']);
    }

    /**
     * Test trên chỉ xác nhận VALIDATION lúc TẠO đơn (đơn trước đó được tạo thẳng trong DB với
     * status=approved, bỏ qua luồng duyệt thật). Test này đi hết chu trình thật: gửi đơn qua
     * HTTP -> duyệt qua HTTP (LeaveRequestsController::approve()) -> xác nhận AnnualLeaveService
     * trừ ĐÚNG theo day_fraction (0.5), không phải trừ cả 1 ngày hay không trừ gì.
     */
    public function test_approving_partial_day_leave_actually_reduces_remaining_balance(): void
    {
        [$morning, $afternoon] = $this->makeTwoShiftsSameDay();
        $this->staffEmployee->update(['joined_at' => now()->subMonths(2)->toDateString()]);

        $service  = app(AnnualLeaveService::class);
        $entitled = $service->entitledDays($this->staffEmployee, now()->year);
        $this->assertEquals($entitled, $service->remainingDays($this->staffEmployee));

        $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from'          => $morning->work_date->toDateString(),
            'date_to'            => $morning->work_date->toDateString(),
            'type'               => 'annual',
            'reason'             => 'Nghỉ ca sáng',
            'is_partial_day'     => 1,
            'shift_schedule_ids' => [$morning->id],
        ])->assertRedirect();

        $leave = LeaveRequest::first();
        $this->assertEquals(0.5, (float) $leave->day_fraction);

        // Còn ở trạng thái pending -> số dư CHƯA bị trừ.
        $this->assertEquals($entitled, $service->remainingDays($this->staffEmployee));

        $this->actingAs($this->manager)->post(route('leave-requests.approve', $leave))->assertRedirect();

        // Sau khi duyệt thật -> số dư giảm ĐÚNG 0.5, không phải cả 1 ngày.
        $this->assertEquals(round($entitled - 0.5, 2), $service->remainingDays($this->staffEmployee));
    }

    /**
     * Nhiều đơn nghỉ theo ca đã duyệt trong cùng năm phải CỘNG DỒN đúng phân số (không làm tròn
     * từng đơn thành nguyên ngày) khi tính usedDays()/remainingDays().
     */
    public function test_multiple_approved_partial_day_leaves_sum_correctly_in_remaining_balance(): void
    {
        $this->staffEmployee->update(['joined_at' => now()->subYears(2)->toDateString()]);
        // 2 ngày khác nhau (không phải cùng ngày) — nếu cùng ngày, cùng date_from/date_to/type thì
        // guard chống double-submit (PreventsDuplicateSubmission) sẽ coi đơn thứ 2 là trùng đơn 1.
        [$day1Morning, , $day2Morning] = $this->makeFourShiftsAcrossTwoDays();

        $service  = app(AnnualLeaveService::class);
        $entitled = $service->entitledDays($this->staffEmployee, now()->year);

        // Nghỉ ca sáng ngày 1 (0.5) + nghỉ ca sáng ngày 2 (0.5) qua 2 đơn riêng biệt.
        $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from' => $day1Morning->work_date->toDateString(), 'date_to' => $day1Morning->work_date->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ sáng A', 'is_partial_day' => 1,
            'shift_schedule_ids' => [$day1Morning->id],
        ])->assertRedirect();

        $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from' => $day2Morning->work_date->toDateString(), 'date_to' => $day2Morning->work_date->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ chiều A', 'is_partial_day' => 1,
            'shift_schedule_ids' => [$day2Morning->id],
        ])->assertRedirect();

        $leaveA = LeaveRequest::where('reason', 'Nghỉ sáng A')->firstOrFail();
        $leaveB = LeaveRequest::where('reason', 'Nghỉ chiều A')->firstOrFail();
        $this->assertEquals(0.5, (float) $leaveA->day_fraction);
        $this->assertEquals(0.5, (float) $leaveB->day_fraction);

        $this->actingAs($this->manager)->post(route('leave-requests.approve', $leaveA))->assertRedirect();
        $this->actingAs($this->manager)->post(route('leave-requests.approve', $leaveB))->assertRedirect();

        // Tổng trừ phải là 0.5 + 0.5 = 1.0, không bị làm tròn hay cộng nhầm.
        $this->assertEquals(round($entitled - 1.0, 2), $service->remainingDays($this->staffEmployee));
    }

    /**
     * Ca WFH gán kiểu "linh hoạt" (shift_id null, custom_is_wfh) trước đây bị đọc nhầm
     * $s->shift->name (null vì không có shift mẫu) khiến nhãn dropdown "Ca làm" trống —
     * nhân viên tưởng như không tìm thấy ca của mình. Xem LeaveRequestsController::shiftsForRange().
     */
    public function test_flexible_wfh_schedule_shows_proper_label_in_shift_picker(): void
    {
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => null,
            'work_date' => now()->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
            'custom_start_time' => '09:00', 'custom_end_time' => '18:00', 'custom_break_minutes' => 60,
            'custom_is_overnight' => false, 'custom_is_wfh' => true,
        ]);

        $response = $this->actingAs($this->staffUser)->getJson(route('leave-requests.shifts-for-range', [
            'employee_id' => $this->staffEmployee->id,
            'date_from'   => now()->toDateString(),
            'date_to'     => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $schedule->id, 'label' => now()->format('d/m/Y') . ' — WFH (ca linh hoạt)']);
    }

    /**
     * Trước đây danh sách ca chỉ nạp sẵn trong cửa sổ -14/+90 ngày quanh hiện tại, nên ca xa hơn
     * khoảng đó không tìm thấy được dù đã xếp thật. Endpoint AJAX tra theo đúng khoảng ngày được
     * chọn nên không còn giới hạn này.
     */
    public function test_shifts_for_range_finds_shift_far_outside_old_fixed_window(): void
    {
        $farDate = now()->addDays(200)->toDateString();
        $shift = Shift::create(['code' => 'CA-XA', 'name' => 'Ca xa', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => $farDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->staffUser)->getJson(route('leave-requests.shifts-for-range', [
            'employee_id' => $this->staffEmployee->id,
            'date_from'   => $farDate,
            'date_to'     => $farDate,
        ]));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $schedule->id]);
    }

    public function test_shifts_for_range_returns_shifts_across_the_whole_range(): void
    {
        [$day1Morning, $day1Afternoon, $day2Morning, $day2Afternoon] = $this->makeFourShiftsAcrossTwoDays();

        $response = $this->actingAs($this->staffUser)->getJson(route('leave-requests.shifts-for-range', [
            'employee_id' => $this->staffEmployee->id,
            'date_from'   => $day1Morning->work_date->toDateString(),
            'date_to'     => $day2Morning->work_date->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonCount(4, 'options');
        foreach ([$day1Morning, $day1Afternoon, $day2Morning, $day2Afternoon] as $s) {
            $response->assertJsonFragment(['id' => $s->id, 'shift_type' => 'parttime']);
        }
    }

    /**
     * Dữ liệu cũ (tạo trước khi có tính năng chọn nhiều ca) vẫn dùng shift_schedule_id đơn +
     * from_time/to_time thủ công — approve() phải xử lý được ngược tương thích: nghỉ nửa ca thì
     * chỉ điều chỉnh khung giờ còn lại, KHÔNG huỷ hẳn ca.
     */
    public function test_approving_legacy_single_shift_leave_still_adjusts_window(): void
    {
        $schedule = $this->makeMorningShiftSchedule(); // Ca 9h-18h

        $leave = LeaveRequest::create([
            'code' => 'LR-LEGACY-01', 'employee_id' => $this->staffEmployee->id,
            'date_from' => $schedule->work_date->toDateString(), 'date_to' => $schedule->work_date->toDateString(),
            'type' => 'annual', 'reason' => 'Nghỉ sáng (dữ liệu cũ)', 'status' => 'pending',
            'is_partial_day' => true, 'from_time' => '09:00', 'to_time' => '12:00',
            'day_fraction' => 0.33, 'shift_schedule_id' => $schedule->id,
        ]);

        $response = $this->actingAs($this->manager)->post(route('leave-requests.approve', $leave));

        $response->assertRedirect();
        $schedule->refresh();
        // Nghỉ từ đầu ca -> chỉ ghi đè giờ bắt đầu, KHÔNG huỷ ca.
        $this->assertEquals('scheduled', $schedule->status);
        $this->assertEquals('12:00', $schedule->adjusted_start_time);
        $this->assertNull($schedule->adjusted_end_time);
    }

    /**
     * Sinh code dựa trên count() bản ghi CÙNG THÁNG — nếu không dùng withTrashed(), xoá 1 đơn
     * ở giữa tháng làm số đếm lùi lại, đơn mới tạo sẽ trùng "code" (unique constraint) với đơn
     * chưa xoá, gây crash 500 khi insert. Xem LeaveRequestsController::store().
     */
    public function test_creating_request_after_soft_delete_does_not_collide_on_code(): void
    {
        // Mỗi lần tạo dùng ngày khác nhau — không phải double-submit thật (đã có guard chặn riêng,
        // xem test_double_submit_creates_only_one_request), chỉ để có 3 bản ghi phân biệt cho kịch bản này.
        $payload = fn(int $offsetDays) => [
            'date_from' => now()->addDays($offsetDays)->toDateString(), 'date_to' => now()->addDays($offsetDays)->toDateString(),
            'type' => 'unpaid', 'reason' => 'x',
        ];

        $this->actingAs($this->staffUser)->post(route('leave-requests.store'), $payload(1));
        $this->actingAs($this->staffUser)->post(route('leave-requests.store'), $payload(2));
        $this->actingAs($this->staffUser)->post(route('leave-requests.store'), $payload(3));

        LeaveRequest::orderBy('id')->skip(1)->first()->delete(); // soft-delete đơn thứ 2

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), $payload(4));

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('leave_requests', 4);
        $codes = LeaveRequest::withTrashed()->pluck('code');
        $this->assertEquals($codes->count(), $codes->unique()->count(), 'Code bị trùng giữa các bản ghi.');
    }

    /**
     * Double-click / gửi lại form khi mạng chậm không được tạo 2 đơn xin nghỉ giống hệt nhau.
     * Xem PreventsDuplicateSubmission::wasJustSubmitted() và LeaveRequestsController::store().
     */
    public function test_double_submit_creates_only_one_request(): void
    {
        $payload = [
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'unpaid', 'reason' => 'Việc gia đình',
        ];

        $this->actingAs($this->staffUser)->post(route('leave-requests.store'), $payload)->assertRedirect();
        $this->actingAs($this->staffUser)->post(route('leave-requests.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('leave_requests', 1);
    }
}
