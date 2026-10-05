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

    public function test_creating_attendance_correction_on_multi_shift_day_requires_shift_schedule_id(): void
    {
        $morningShift = Shift::create([
            'code' => 'CA-S2', 'name' => 'Ca sáng', 'start_time' => '11:00', 'end_time' => '15:00', 'work_mode' => 'onsite',
        ]);
        $eveningShift = Shift::create([
            'code' => 'CA-T2', 'name' => 'Ca tối', 'start_time' => '17:30', 'end_time' => '23:30', 'work_mode' => 'onsite',
        ]);
        $workDate = now()->toDateString();
        ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $morningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $eveningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'        => 'attendance_correction',
            'work_date'   => $workDate,
            'check_in_at' => '17:26',
            'reason'      => 'Sửa giờ vào ca tối',
        ]);

        $response->assertSessionHasErrors('shift_schedule_id');
    }

    public function test_creating_attendance_correction_on_multi_shift_day_with_shift_schedule_id_saves_it_to_payload(): void
    {
        $morningShift = Shift::create([
            'code' => 'CA-S4', 'name' => 'Ca sáng', 'start_time' => '11:00', 'end_time' => '15:00', 'work_mode' => 'onsite',
        ]);
        $eveningShift = Shift::create([
            'code' => 'CA-T4', 'name' => 'Ca tối', 'start_time' => '17:30', 'end_time' => '23:30', 'work_mode' => 'onsite',
        ]);
        $workDate = now()->toDateString();
        ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $morningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $eveningSchedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $eveningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        // Test này gọi thẳng route staff-requests.store (không tạo trực tiếp qua StaffRequest::create()
        // như các test khác) để bắt được bug thực tế đã gặp: field không khai báo rule trong
        // StoreStaffRequestRequest::rules() sẽ bị Laravel loại khỏi $request->validated(), dù có
        // gửi lên và có logic kiểm tra riêng trong withValidator().
        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'              => 'attendance_correction',
            'work_date'         => $workDate,
            'check_in_at'       => '17:40',
            'shift_schedule_id' => $eveningSchedule->id,
            'reason'            => 'Sửa giờ vào ca tối',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', [
            'employee_id' => $this->staffEmployee->id,
            'type'        => 'attendance_correction',
        ]);
        $this->assertEquals(
            ['check_in_at' => '17:40', 'shift_schedule_id' => $eveningSchedule->id],
            StaffRequest::latest('id')->first()->payload
        );
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

    public function test_employee_can_create_overtime_request(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'         => 'overtime',
            'work_date'    => now()->toDateString(),
            'ot_from_time' => '18:00',
            'ot_to_time'   => '20:30',
            'reason'       => 'Hỗ trợ sự kiện buổi tối',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', ['type' => 'overtime', 'status' => 'pending']);
        $this->assertEquals(['from_time' => '18:00', 'to_time' => '20:30'], StaffRequest::first()->payload);
    }

    public function test_overtime_to_time_same_as_from_time_is_rejected(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'         => 'overtime',
            'work_date'    => now()->toDateString(),
            'ot_from_time' => '20:30',
            'ot_to_time'   => '20:30',
            'reason'       => 'Hỗ trợ sự kiện buổi tối',
        ]);

        $response->assertSessionHasErrors('ot_to_time');
    }

    public function test_overtime_spanning_more_than_sixteen_hours_is_rejected(): void
    {
        // 08:00 hôm nay -> 03:00 hôm sau (hiểu là qua đêm) = 19 giờ, vượt quá 16 giờ cho phép.
        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'         => 'overtime',
            'work_date'    => now()->toDateString(),
            'ot_from_time' => '08:00',
            'ot_to_time'   => '03:00',
            'reason'       => 'Hỗ trợ sự kiện dài ngày',
        ]);

        $response->assertSessionHasErrors('ot_to_time');
    }

    /**
     * Tăng ca qua đêm (VD 23:00 hôm nay -> 03:00 hôm sau) trước đây bị chặn hoàn toàn bởi rule
     * "after" (so sánh 2 giờ như cùng 1 ngày, 03:00 không "sau" 23:00) — nay được hiểu là kéo
     * dài sang ngày hôm sau, giống cách Shift::is_overnight xử lý ca qua đêm.
     */
    public function test_employee_can_create_overnight_overtime_request(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), [
            'type'         => 'overtime',
            'work_date'    => now()->toDateString(),
            'ot_from_time' => '23:00',
            'ot_to_time'   => '03:00',
            'reason'       => 'Trực ca đêm sự kiện',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('staff_requests', ['type' => 'overtime', 'status' => 'pending']);
        $staffRequest = StaffRequest::first();
        $this->assertEquals(['from_time' => '23:00', 'to_time' => '03:00'], $staffRequest->payload);
        $this->assertTrue($staffRequest->isOvernightOvertime());
        $this->assertEquals(4.0, $staffRequest->overtimeHours());
    }

    public function test_manager_approving_overnight_overtime_credits_four_hours_to_the_start_date(): void
    {
        $workDate = now()->toDateString();
        $staffRequest = StaffRequest::create([
            'code' => 'OT-TEST-0003', 'employee_id' => $this->staffEmployee->id,
            'type' => 'overtime', 'work_date' => $workDate,
            'payload' => ['from_time' => '23:00', 'to_time' => '03:00'], 'reason' => 'Trực ca đêm', 'status' => 'pending',
        ]);

        $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest))->assertRedirect();

        // Công tăng ca qua đêm được cộng vào đúng ngày bắt đầu (work_date), không phải ngày hôm sau.
        $log = AttendanceLog::where('employee_id', $this->staffEmployee->id)->where('work_date', $workDate)->first();
        $this->assertNotNull($log);
        $this->assertEquals(4.0, (float) $log->overtime_hours);
    }

    public function test_manager_approving_overtime_adds_hours_to_existing_attendance_log(): void
    {
        $shift = Shift::create([
            'code' => 'CA-HC2', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00',
            'work_mode' => 'onsite', 'standard_work_hours' => 8, 'break_minutes' => 60,
        ]);
        $workDate = now()->toDateString();
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $this->staffEmployee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $workDate,
            'check_in_at' => $workDate . ' 08:00:00', 'check_out_at' => $workDate . ' 17:00:00',
        ]);
        $this->assertEquals(1.0, $log->fresh()->computeCong($shift));

        $staffRequest = StaffRequest::create([
            'code' => 'OT-TEST-0001', 'employee_id' => $this->staffEmployee->id,
            'type' => 'overtime', 'work_date' => $workDate,
            'payload' => ['from_time' => '18:00', 'to_time' => '22:00'], 'reason' => 'Tăng ca', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest));

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', ['id' => $staffRequest->id, 'status' => 'approved']);

        $log = $log->fresh();
        $this->assertEquals(4.0, (float) $log->overtime_hours);
        // 1 công thường + 4h tăng ca / 8h chuẩn = 1 + 0.5 = 1.5 công
        $this->assertEquals(1.5, $log->computeCong($shift));
    }

    public function test_manager_approving_overtime_on_day_off_creates_log_with_only_overtime_cong(): void
    {
        $workDate = now()->toDateString();
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $this->staffEmployee->id, 'work_date' => $workDate]);

        $staffRequest = StaffRequest::create([
            'code' => 'OT-TEST-0002', 'employee_id' => $this->staffEmployee->id,
            'type' => 'overtime', 'work_date' => $workDate,
            'payload' => ['from_time' => '09:00', 'to_time' => '13:00'], 'reason' => 'Tăng ca ngày nghỉ', 'status' => 'pending',
        ]);

        $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest))->assertRedirect();

        $log = AttendanceLog::where('employee_id', $this->staffEmployee->id)->where('work_date', $workDate)->first();
        $this->assertNotNull($log);
        $this->assertNull($log->check_in_at);
        $this->assertEquals(4.0, (float) $log->overtime_hours);
        // Không có ca/giờ chuẩn xác định -> mặc định 8h: 4h tăng ca / 8h = 0.5 công
        $this->assertEquals(0.5, $log->computeCong());
    }

    /** Cấp quyền xoá yêu cầu đã duyệt (admin) cho manager trong các test đảo ngược. */
    private function grantApprovedDeletePermissions(): void
    {
        $this->manager->givePermissionTo(Permission::firstOrCreate(['name' => 'delete-staff-requests']));
        $this->manager->givePermissionTo(Permission::firstOrCreate(['name' => 'delete-approved-requests']));
    }

    public function test_deleting_approved_overtime_reverts_added_hours_on_existing_log(): void
    {
        $this->grantApprovedDeletePermissions();
        $shift = Shift::create([
            'code' => 'CA-OT-R', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00',
            'work_mode' => 'onsite', 'standard_work_hours' => 8, 'break_minutes' => 60,
        ]);
        $workDate = now()->toDateString();
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $this->staffEmployee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $workDate,
            'check_in_at' => $workDate . ' 08:00:00', 'check_out_at' => $workDate . ' 17:00:00',
        ]);

        $sr = StaffRequest::create([
            'code' => 'OT-REV-0001', 'employee_id' => $this->staffEmployee->id, 'type' => 'overtime',
            'work_date' => $workDate, 'payload' => ['from_time' => '18:00', 'to_time' => '22:00'], 'reason' => 'OT', 'status' => 'pending',
        ]);
        $this->actingAs($this->manager)->post(route('staff-requests.approve', $sr))->assertRedirect();
        $this->assertEquals(4.0, (float) $log->fresh()->overtime_hours);

        $this->actingAs($this->manager)->delete(route('staff-requests.destroy', $sr))->assertRedirect();

        $this->assertSoftDeleted('staff_requests', ['id' => $sr->id]);
        // Log gốc vẫn còn, giờ tăng ca đã được trừ về 0.
        $this->assertNotNull($log->fresh());
        $this->assertEquals(0.0, (float) $log->fresh()->overtime_hours);
    }

    public function test_deleting_approved_overtime_on_day_off_deletes_created_log(): void
    {
        $this->grantApprovedDeletePermissions();
        $workDate = now()->toDateString();

        $sr = StaffRequest::create([
            'code' => 'OT-REV-0002', 'employee_id' => $this->staffEmployee->id, 'type' => 'overtime',
            'work_date' => $workDate, 'payload' => ['from_time' => '09:00', 'to_time' => '13:00'], 'reason' => 'OT ngày nghỉ', 'status' => 'pending',
        ]);
        $this->actingAs($this->manager)->post(route('staff-requests.approve', $sr))->assertRedirect();
        $this->assertDatabaseHas('attendance_logs', ['employee_id' => $this->staffEmployee->id, 'work_date' => $workDate]);

        $this->actingAs($this->manager)->delete(route('staff-requests.destroy', $sr))->assertRedirect();

        // Log do chính đơn tăng ca này tạo ra -> xoá đơn thì xoá luôn log.
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $this->staffEmployee->id, 'work_date' => $workDate]);
    }

    public function test_deleting_approved_late_early_forgiveness_restores_penalty(): void
    {
        $this->grantApprovedDeletePermissions();
        $shift = Shift::create([
            'code' => 'CA-LE-R', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00',
            'work_mode' => 'onsite', 'grace_late_minutes' => 5,
        ]);
        $workDate = now()->toDateString();
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $this->staffEmployee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $workDate,
            'check_in_at' => $workDate . ' 08:20:00', 'late_minutes' => 15, 'full_credit' => false,
        ]);

        $sr = StaffRequest::create([
            'code' => 'LE-REV-0001', 'employee_id' => $this->staffEmployee->id, 'type' => 'late_early',
            'work_date' => $workDate, 'payload' => ['mode' => 'late', 'minutes' => 15, 'shift_schedule_id' => $schedule->id],
            'reason' => 'Kẹt xe', 'status' => 'pending',
        ]);
        $this->actingAs($this->manager)->post(route('staff-requests.approve', $sr), ['outcome' => 'normal'])->assertRedirect();

        $log->refresh();
        $this->assertEquals(0, $log->late_minutes);
        $this->assertTrue((bool) $log->full_credit);

        $this->actingAs($this->manager)->delete(route('staff-requests.destroy', $sr))->assertRedirect();

        // Đảo ngược tha lỗi: khôi phục late_minutes cũ + bỏ full_credit.
        $log->refresh();
        $this->assertEquals(15, $log->late_minutes);
        $this->assertFalse((bool) $log->full_credit);
    }

    public function test_deleting_approved_attendance_correction_deletes_created_log(): void
    {
        $this->grantApprovedDeletePermissions();
        $workDate = now()->toDateString();

        $sr = StaffRequest::create([
            'code' => 'ATC-REV-0001', 'employee_id' => $this->staffEmployee->id, 'type' => 'attendance_correction',
            'work_date' => $workDate, 'payload' => ['check_in_at' => '08:05'], 'reason' => 'Quên chấm', 'status' => 'pending',
        ]);
        $this->actingAs($this->manager)->post(route('staff-requests.approve', $sr))->assertRedirect();
        $this->assertDatabaseHas('attendance_logs', ['employee_id' => $this->staffEmployee->id, 'work_date' => $workDate]);

        $this->actingAs($this->manager)->delete(route('staff-requests.destroy', $sr))->assertRedirect();

        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $this->staffEmployee->id, 'work_date' => $workDate]);
    }

    public function test_cannot_delete_approved_request_when_a_newer_request_touched_same_log(): void
    {
        $this->grantApprovedDeletePermissions();
        $shift = Shift::create([
            'code' => 'CA-CONF', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00',
            'work_mode' => 'onsite', 'standard_work_hours' => 8, 'break_minutes' => 60,
        ]);
        $workDate = now()->toDateString();
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $this->staffEmployee->id, 'shift_schedule_id' => $schedule->id, 'work_date' => $workDate,
            'check_in_at' => $workDate . ' 08:00:00', 'check_out_at' => $workDate . ' 17:00:00',
        ]);

        $sr1 = StaffRequest::create([
            'code' => 'OT-CONF-1', 'employee_id' => $this->staffEmployee->id, 'type' => 'overtime',
            'work_date' => $workDate, 'payload' => ['from_time' => '18:00', 'to_time' => '20:00'], 'reason' => 'OT 1', 'status' => 'pending',
        ]);
        $this->actingAs($this->manager)->post(route('staff-requests.approve', $sr1))->assertRedirect();

        $sr2 = StaffRequest::create([
            'code' => 'OT-CONF-2', 'employee_id' => $this->staffEmployee->id, 'type' => 'overtime',
            'work_date' => $workDate, 'payload' => ['from_time' => '20:00', 'to_time' => '22:00'], 'reason' => 'OT 2', 'status' => 'pending',
        ]);
        $this->actingAs($this->manager)->post(route('staff-requests.approve', $sr2))->assertRedirect();

        // Xoá đơn CŨ (sr1) trong khi đơn MỚI HƠN (sr2) cũng đã ghi vào cùng log -> chặn 422.
        $this->actingAs($this->manager)->delete(route('staff-requests.destroy', $sr1))->assertStatus(422);
        $this->assertDatabaseHas('staff_requests', ['id' => $sr1->id, 'deleted_at' => null]);

        // Xoá đơn MỚI HƠN (sr2) trước thì được, và trừ đúng 2h nó đã cộng.
        $this->assertEquals(4.0, (float) $log->fresh()->overtime_hours);
        $this->actingAs($this->manager)->delete(route('staff-requests.destroy', $sr2))->assertRedirect();
        $this->assertEquals(2.0, (float) $log->fresh()->overtime_hours);
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

    public function test_manager_approving_attendance_correction_with_shift_schedule_id_updates_correct_shift(): void
    {
        // Nhân viên đa ca cùng ngày, yêu cầu chọn đúng ca tối (payload.shift_schedule_id) — duyệt
        // phải sửa đúng lượt chấm công ca tối, không được đụng vào lượt chấm công ca sáng.
        $morningShift = Shift::create([
            'code' => 'CA-S3', 'name' => 'Ca sáng', 'start_time' => '11:00', 'end_time' => '15:00', 'work_mode' => 'onsite',
        ]);
        $eveningShift = Shift::create([
            'code' => 'CA-T3', 'name' => 'Ca tối', 'start_time' => '17:30', 'end_time' => '23:30',
            'work_mode' => 'onsite', 'grace_late_minutes' => 5,
        ]);
        $workDate = now()->toDateString();
        $morningSchedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $morningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $eveningSchedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $eveningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $morningLog = AttendanceLog::create([
            'employee_id' => $this->staffEmployee->id, 'shift_schedule_id' => $morningSchedule->id,
            'work_date' => $workDate, 'check_in_at' => $workDate . ' 11:00:00', 'check_out_at' => $workDate . ' 15:00:00',
            'check_in_method' => 'gps', 'check_out_method' => 'gps', 'late_minutes' => 0, 'early_minutes' => 0,
        ]);
        $eveningLog = AttendanceLog::create([
            'employee_id' => $this->staffEmployee->id, 'shift_schedule_id' => $eveningSchedule->id,
            'work_date' => $workDate, 'check_in_at' => $workDate . ' 17:30:00', 'check_out_at' => $workDate . ' 23:30:00',
            'check_in_method' => 'gps', 'check_out_method' => 'gps', 'late_minutes' => 0, 'early_minutes' => 0,
        ]);

        $staffRequest = StaffRequest::create([
            'code' => 'ATC-TEST-0002', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => $workDate,
            'payload' => ['check_in_at' => '17:50', 'shift_schedule_id' => $eveningSchedule->id],
            'reason' => 'Sửa giờ vào ca tối', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest));
        $response->assertRedirect();

        $morningLog->refresh();
        $eveningLog->refresh();

        $this->assertEquals('11:00:00', $morningLog->check_in_at->format('H:i:s')); // không đổi
        $this->assertEquals('17:50:00', $eveningLog->check_in_at->format('H:i:s'));
        $this->assertEquals(15, $eveningLog->late_minutes); // 20 phút trễ - 5 phút cho phép
    }

    public function test_manager_approving_time_change_still_counts_late_minutes(): void
    {
        // time_change chỉ xác nhận "giờ vào/ra thực tế là X", KHÔNG tự động tha lỗi trễ/sớm —
        // giờ mới trễ hơn ca thì vẫn phải tính trễ như bình thường (xem applyTimeChange()).
        $shift = Shift::create([
            'code' => 'CA-HC2', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00',
            'work_mode' => 'onsite', 'grace_late_minutes' => 5,
        ]);
        $workDate = now()->toDateString();
        ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $staffRequest = StaffRequest::create([
            'code' => 'TC-TEST-0001', 'employee_id' => $this->staffEmployee->id,
            'type' => 'time_change', 'work_date' => $workDate,
            'payload' => ['new_check_in' => '10:00', 'new_check_out' => '19:00'],
            'reason' => 'Đưa con đi học', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest));

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_requests', ['id' => $staffRequest->id, 'status' => 'approved']);

        $log = AttendanceLog::where('employee_id', $this->staffEmployee->id)->where('work_date', $workDate)->first();
        $this->assertNotNull($log);
        $this->assertEquals('10:00:00', $log->check_in_at->format('H:i:s'));
        $this->assertEquals('19:00:00', $log->check_out_at->format('H:i:s'));
        $this->assertEquals('manual', $log->check_in_method);
        $this->assertEquals('manual', $log->check_out_method);
        $this->assertEquals(115, $log->late_minutes); // 10:00 - 08:00 = 120 phút - 5 phút cho phép
        $this->assertEquals(0, $log->early_minutes); // 19:00 sau giờ kết thúc ca (17:00) -> không tính sớm
        $this->assertFalse((bool) $log->full_credit);
        $this->assertEquals('Ghi nhận', $staffRequest->fresh()->correctionOutcomeLabel());
    }

    public function test_manager_approving_time_change_within_grace_has_zero_late_minutes(): void
    {
        $shift = Shift::create([
            'code' => 'CA-HC3', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00',
            'work_mode' => 'onsite', 'grace_late_minutes' => 5,
        ]);
        $workDate = now()->toDateString();
        ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $staffRequest = StaffRequest::create([
            'code' => 'TC-TEST-0002', 'employee_id' => $this->staffEmployee->id,
            'type' => 'time_change', 'work_date' => $workDate,
            'payload' => ['new_check_in' => '08:03', 'new_check_out' => '17:00'],
            'reason' => 'Sửa giờ vào/ra đúng thực tế', 'status' => 'pending',
        ]);

        $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest));

        $log = AttendanceLog::where('employee_id', $this->staffEmployee->id)->where('work_date', $workDate)->first();
        $this->assertEquals(0, $log->late_minutes); // trong 5 phút cho phép
        $this->assertEquals(0, $log->early_minutes);
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

    public function test_manager_approving_late_early_forgiveness_applies_to_correct_shift_on_multi_shift_day(): void
    {
        // Nhân viên xếp đa ca trong cùng 1 ngày: ca sáng đi làm đúng giờ, ca tối về sớm — yêu cầu
        // "Đi muộn về sớm" (mode=early) phải tha lỗi đúng lượt chấm công ca tối, không được lấy đại
        // lượt đầu tiên trong ngày (ca sáng) như bug thực tế đã gặp.
        $morningShift = Shift::create([
            'code' => 'CA-S', 'name' => 'Ca sáng', 'start_time' => '11:00', 'end_time' => '15:00',
            'work_mode' => 'onsite', 'standard_work_hours' => 10,
        ]);
        $eveningShift = Shift::create([
            'code' => 'CA-T', 'name' => 'Ca tối', 'start_time' => '17:30', 'end_time' => '23:30',
            'work_mode' => 'onsite', 'standard_work_hours' => 10,
        ]);
        $workDate = now()->toDateString();

        $morningSchedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $morningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $eveningSchedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $eveningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $morningLog = AttendanceLog::create([
            'employee_id' => $this->staffEmployee->id, 'shift_schedule_id' => $morningSchedule->id,
            'work_date' => $workDate,
            'check_in_at' => $workDate . ' 11:00:00', 'check_out_at' => $workDate . ' 15:00:00',
            'check_in_method' => 'gps', 'check_out_method' => 'gps',
            'late_minutes' => 0, 'early_minutes' => 0,
        ]);
        $eveningLog = AttendanceLog::create([
            'employee_id' => $this->staffEmployee->id, 'shift_schedule_id' => $eveningSchedule->id,
            'work_date' => $workDate,
            'check_in_at' => $workDate . ' 17:26:00', 'check_out_at' => $workDate . ' 22:20:00',
            'check_in_method' => 'gps', 'check_out_method' => 'gps',
            'late_minutes' => 0, 'early_minutes' => 69,
        ]);

        $staffRequest = StaffRequest::create([
            'code' => 'LE-TEST-0001', 'employee_id' => $this->staffEmployee->id,
            'type' => 'late_early', 'work_date' => $workDate,
            'payload' => ['mode' => 'early', 'minutes' => 90], 'reason' => 'Bận việc cá nhân', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest), [
            'outcome' => 'normal',
        ]);

        $response->assertRedirect();

        $morningLog->refresh();
        $eveningLog->refresh();

        $this->assertFalse($morningLog->full_credit);
        $this->assertEquals(0, $morningLog->early_minutes);

        $this->assertTrue($eveningLog->full_credit);
        $this->assertEquals(0, $eveningLog->early_minutes);
        $this->assertEquals('Đã tha lỗi', $staffRequest->fresh()->correctionOutcomeLabel());
    }

    public function test_late_early_approved_with_actual_outcome_still_counts_late_and_shows_ghi_nhan_label(): void
    {
        // Kết quả "Trừ giờ thực tế" (outcome=actual): KHÔNG tha lỗi — AttendanceLog giữ nguyên
        // trễ/sớm, chỉ đánh dấu đã có đơn được duyệt (correctionOutcomeLabel = "Ghi nhận", khác với
        // "Đã tha lỗi" của outcome=normal).
        $shift = Shift::create([
            'code' => 'CA-HC4', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);
        $workDate = now()->toDateString();
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $this->staffEmployee->id, 'shift_schedule_id' => $schedule->id,
            'work_date' => $workDate,
            'check_in_at' => $workDate . ' 08:30:00', 'check_out_at' => $workDate . ' 17:00:00',
            'check_in_method' => 'gps', 'check_out_method' => 'gps',
            'late_minutes' => 30, 'early_minutes' => 0,
        ]);

        $staffRequest = StaffRequest::create([
            'code' => 'LE-TEST-0002', 'employee_id' => $this->staffEmployee->id,
            'type' => 'late_early', 'work_date' => $workDate,
            'payload' => ['mode' => 'late', 'minutes' => 30], 'reason' => 'Kẹt xe', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest), [
            'outcome' => 'actual',
        ]);
        $response->assertRedirect();

        $log->refresh();
        $this->assertFalse((bool) $log->full_credit);
        $this->assertEquals(30, $log->late_minutes); // vẫn tính trễ, không bị xoá
        $this->assertEquals('Ghi nhận', $staffRequest->fresh()->correctionOutcomeLabel());

        $listResponse = $this->actingAs($this->manager)->get(route('staff-requests.index'));
        $listResponse->assertStatus(200);
        $listResponse->assertSee('Ghi nhận', false);
    }

    public function test_manager_approving_attendance_correction_for_overnight_shift_rolls_checkout_to_next_day(): void
    {
        // Tái hiện đúng bug thực tế: ca "Ca Bếp tối" 18h-24h, yêu cầu "Lượt chấm công" ghi giờ vào
        // 17:57 và giờ ra 00:10 (rạng sáng hôm sau) — duyệt xong phải lưu check_out_at vào ĐÚNG
        // ngày hôm sau work_date, không phải cùng ngày với giờ vào ca.
        $shift = Shift::create([
            'code' => 'CA-BEP-TOI', 'name' => 'Ca Bếp tối', 'start_time' => '18:00', 'end_time' => '00:00',
            'work_mode' => 'onsite',
        ]);
        $workDate = now()->toDateString();
        ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $staffRequest = StaffRequest::create([
            'code' => 'ATC-TEST-0099', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => $workDate,
            'payload' => ['check_in_at' => '17:57', 'check_out_at' => '00:10'],
            'reason' => 'Quên chấm công ca đêm', 'status' => 'pending',
        ]);

        $this->actingAs($this->manager)->post(route('staff-requests.approve', $staffRequest))->assertRedirect();

        $log = AttendanceLog::where('employee_id', $this->staffEmployee->id)->where('work_date', $workDate)->first();
        $this->assertNotNull($log);
        $this->assertEquals($workDate . ' 17:57', $log->check_in_at->format('Y-m-d H:i'));
        $this->assertEquals(
            \Carbon\Carbon::parse($workDate)->addDay()->format('Y-m-d') . ' 00:10',
            $log->check_out_at->format('Y-m-d H:i')
        );
        $this->assertEquals(6.0, $log->netWorkedHours());
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

    public function test_owner_cannot_cancel_approved_request_without_delete_permission(): void
    {
        $staffRequest = StaffRequest::create([
            'code' => 'ATC-TEST-0004', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'payload' => ['check_in_at' => '08:05'], 'reason' => 'X', 'status' => 'approved',
            'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->staffUser)->delete(route('staff-requests.destroy', $staffRequest));

        $response->assertStatus(403);
        $this->assertDatabaseHas('staff_requests', ['id' => $staffRequest->id, 'deleted_at' => null]);
    }

    public function test_uninvolved_employee_cannot_delete_others_pending_request(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');
        $otherEmployee = Employee::create(['code' => 'EMP-03', 'name' => 'Lê Văn C', 'user_id' => $otherUser->id, 'is_active' => true]);

        $staffRequest = StaffRequest::create([
            'code' => 'ATC-TEST-0005', 'employee_id' => $otherEmployee->id,
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'payload' => ['check_in_at' => '08:05'], 'reason' => 'X', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staffUser)->delete(route('staff-requests.destroy', $staffRequest));

        $response->assertStatus(403);
        $this->assertDatabaseHas('staff_requests', ['id' => $staffRequest->id, 'deleted_at' => null]);
    }

    public function test_admin_permission_can_purge_approved_request(): void
    {
        $this->manager->givePermissionTo(Permission::firstOrCreate(['name' => 'delete-staff-requests']));
        $this->manager->givePermissionTo(Permission::firstOrCreate(['name' => 'delete-approved-requests']));

        $staffRequest = StaffRequest::create([
            'code' => 'ATC-TEST-0006', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'payload' => ['check_in_at' => '08:05'], 'reason' => 'X', 'status' => 'approved',
            'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)->delete(route('staff-requests.destroy', $staffRequest));

        $response->assertRedirect();
        $this->assertSoftDeleted('staff_requests', ['id' => $staffRequest->id]);
    }

    public function test_delete_staff_permission_alone_cannot_delete_approved_request(): void
    {
        $this->manager->givePermissionTo(Permission::firstOrCreate(['name' => 'delete-staff-requests']));

        $staffRequest = StaffRequest::create([
            'code' => 'ATC-TEST-0006B', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'payload' => ['check_in_at' => '08:05'], 'reason' => 'X', 'status' => 'approved',
            'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)->delete(route('staff-requests.destroy', $staffRequest));

        $response->assertStatus(403);
        $this->assertDatabaseHas('staff_requests', ['id' => $staffRequest->id, 'deleted_at' => null]);
    }

    public function test_manager_without_delete_permission_cannot_purge_pending_request_of_others(): void
    {
        $staffRequest = StaffRequest::create([
            'code' => 'ATC-TEST-0007', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'payload' => ['check_in_at' => '08:05'], 'reason' => 'X', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('staff-requests.destroy', $staffRequest));

        $response->assertStatus(403);
        $this->assertDatabaseHas('staff_requests', ['id' => $staffRequest->id, 'deleted_at' => null]);
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

    /**
     * Sinh code dựa trên count() các bản ghi CÙNG THÁNG — nếu không dùng withTrashed(), xoá 1
     * phiếu ở giữa tháng làm số đếm lùi lại, phiếu mới tạo sẽ trùng "code" (unique constraint)
     * với phiếu chưa xoá, gây crash 500 khi insert. Xem StaffRequestsController::store().
     */
    public function test_creating_request_after_soft_delete_does_not_collide_on_code(): void
    {
        // Mỗi lần tạo dùng reason khác nhau — không phải double-submit thật (đã có guard chặn riêng,
        // xem test_double_submit_creates_only_one_request), chỉ để có 3 bản ghi phân biệt cho kịch bản này.
        $payload = fn(string $reason) => [
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'check_in_at' => '08:05', 'reason' => $reason,
        ];

        $this->actingAs($this->staffUser)->post(route('staff-requests.store'), $payload('a'));
        $this->actingAs($this->staffUser)->post(route('staff-requests.store'), $payload('b'));
        $this->actingAs($this->staffUser)->post(route('staff-requests.store'), $payload('c'));

        StaffRequest::orderBy('id')->skip(1)->first()->delete(); // soft-delete phiếu thứ 2

        $response = $this->actingAs($this->staffUser)->post(route('staff-requests.store'), $payload('d'));

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('staff_requests', 4); // 3 phiếu đầu (1 đã xoá mềm, vẫn còn dòng vật lý) + 1 phiếu vừa tạo
        $codes = StaffRequest::withTrashed()->pluck('code');
        $this->assertEquals($codes->count(), $codes->unique()->count(), 'Code bị trùng giữa các bản ghi.');
    }

    /**
     * Double-click / gửi lại form khi mạng chậm không được tạo 2 yêu cầu giống hệt nhau.
     * Xem PreventsDuplicateSubmission::wasJustSubmitted() và StaffRequestsController::store().
     */
    public function test_double_submit_creates_only_one_request(): void
    {
        $payload = [
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'check_in_at' => '08:05', 'reason' => 'Quên chấm công',
        ];

        $this->actingAs($this->staffUser)->post(route('staff-requests.store'), $payload)->assertRedirect();
        $this->actingAs($this->staffUser)->post(route('staff-requests.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('staff_requests', 1);
    }

    /**
     * Form "Nghỉ phép" trong hub (srLeaveForm) trước đây thiếu hẳn checkbox "Chỉ nghỉ theo giờ"
     * và ô Từ giờ/Đến giờ — nhân viên chỉ tạo được nghỉ nửa ngày qua trang /leave-requests riêng,
     * không tạo được từ hub (nơi hầu hết mọi người dùng). Xem staff-requests/index.blade.php.
     */
    public function test_hub_leave_form_includes_partial_day_fields(): void
    {
        $response = $this->actingAs($this->staffUser)->get(route('staff-requests.index'));

        $response->assertOk();
        $response->assertSee('name="is_partial_day"', false);
        $response->assertSee('srLeavePartialToggle', false);
        $response->assertSee('id="srLeaveFromTime"', false);
        $response->assertSee('id="srLeaveToTime"', false);
        // Ca đã xếp lấy qua AJAX (không còn tải sẵn toàn bộ danh sách) + gắn cờ khối văn phòng để
        // JS chỉ hiện checkbox "Chỉ nghỉ theo giờ" cho đúng NV văn phòng.
        $response->assertSee('data-shifts-url="' . route('leave-requests.shifts-for-range') . '"', false);
        $response->assertSee('id="srLeaveOfficeData"', false);
    }

    /**
     * Đóng đúng lỗ hổng ở comment phía trên: form "Nghỉ phép" trong hub gửi shift_schedule_id
     * (số ít) + from_time/to_time — phải đi kèm partial_mode=custom_time (hidden input cố định
     * trong srLeaveForm) để LeaveRequestsController::store() xử lý đúng nhánh, không rơi vào
     * nhánh "shifts" (đòi hỏi shift_schedule_ids[] dạng mảng) và bị từ chối.
     */
    public function test_hub_leave_form_payload_creates_half_day_office_leave(): void
    {
        Role::where('name', 'staff')->first()->givePermissionTo(Permission::firstOrCreate(['name' => 'create-leave-requests']));
        // store() gọi auth()->user()->can('approve-leave-requests'|'approve-shift-swaps') để xác
        // định có phải approver không — permission phải tồn tại trước, kể cả khi không gán cho ai.
        Permission::firstOrCreate(['name' => 'approve-leave-requests']);
        Permission::firstOrCreate(['name' => 'approve-shift-swaps']);
        $this->staffEmployee->update(['employment_type' => 'full_time', 'is_office' => true]);

        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính',
            'start_time' => '09:00', 'end_time' => '18:00', 'break_minutes' => 60, 'work_mode' => 'onsite',
        ]);
        $schedule = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $shift->id,
            'work_date' => now()->addDays(2)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from'         => $schedule->work_date->toDateString(),
            'date_to'           => $schedule->work_date->toDateString(),
            'type'              => 'annual',
            'reason'            => 'Đi khám bệnh buổi sáng',
            'is_partial_day'    => 1,
            'partial_mode'      => 'custom_time',
            'shift_schedule_id' => $schedule->id,
            'from_time'         => '09:00',
            'to_time'           => '12:00',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('leave_requests', [
            'employee_id'       => $this->staffEmployee->id,
            'shift_schedule_id' => $schedule->id,
        ]);
    }

    /**
     * NV không thuộc khối văn phòng (VD Bar/Bếp làm nhiều ca/ngày) không có mode "nửa ngày theo
     * giờ" nhưng vẫn phải chọn được "ca cụ thể" cần nghỉ (mode "shifts", mặc định của hub) — đây
     * chính là phần chọn ca đã có từ trước, không phụ thuộc is_office.
     */
    public function test_hub_leave_form_shifts_mode_works_for_non_office_employee(): void
    {
        Role::where('name', 'staff')->first()->givePermissionTo(Permission::firstOrCreate(['name' => 'create-leave-requests']));
        Permission::firstOrCreate(['name' => 'approve-leave-requests']);
        Permission::firstOrCreate(['name' => 'approve-shift-swaps']);
        $this->staffEmployee->update(['is_office' => false]);

        $morningShift = Shift::create(['code' => 'CA-S', 'name' => 'Ca sáng', 'start_time' => '08:00', 'end_time' => '12:00', 'work_mode' => 'onsite']);
        $afternoonShift = Shift::create(['code' => 'CA-C', 'name' => 'Ca chiều', 'start_time' => '13:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        $workDate = now()->addDays(2)->toDateString();
        $morning = ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $morningShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        ShiftSchedule::create([
            'employee_id' => $this->staffEmployee->id, 'shift_id' => $afternoonShift->id,
            'work_date' => $workDate, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->actingAs($this->staffUser)->post(route('leave-requests.store'), [
            'date_from'          => $workDate,
            'date_to'            => $workDate,
            'type'               => 'unpaid',
            'reason'             => 'Chỉ nghỉ ca sáng',
            'is_partial_day'     => 1,
            'partial_mode'       => 'shifts',
            'shift_schedule_ids' => [$morning->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('leave_requests', ['employee_id' => $this->staffEmployee->id]);
    }

    public function test_owner_can_update_own_pending_request(): void
    {
        $sr = StaffRequest::create([
            'code' => 'ATC-202601-0001', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'payload' => ['check_in_at' => '08:05'], 'reason' => 'Quên chấm công', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staffUser)->put(route('staff-requests.update', $sr), [
            'work_date'   => now()->toDateString(),
            'check_in_at' => '08:15',
            'reason'      => 'Quên chấm công (đã sửa lại giờ)',
        ]);

        $response->assertRedirect();
        $sr->refresh();
        $this->assertEquals(['check_in_at' => '08:15'], $sr->payload);
        $this->assertEquals('Quên chấm công (đã sửa lại giờ)', $sr->reason);
        $this->assertEquals('pending', $sr->status);
    }

    public function test_owner_cannot_update_own_approved_request_without_edit_permission(): void
    {
        $sr = StaffRequest::create([
            'code' => 'ATC-202601-0002', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'payload' => ['check_in_at' => '08:05'], 'reason' => 'Quên chấm công', 'status' => 'approved',
            'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->staffUser)->put(route('staff-requests.update', $sr), [
            'work_date' => now()->toDateString(), 'check_in_at' => '08:15', 'reason' => 'Sửa lại',
        ]);

        $response->assertStatus(403);
    }

    public function test_uninvolved_employee_cannot_update_others_request(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('staff');
        $otherEmployee = Employee::create(['code' => 'EMP-03', 'name' => 'Lê Văn C', 'user_id' => $otherUser->id, 'is_active' => true]);

        $sr = StaffRequest::create([
            'code' => 'ATC-202601-0003', 'employee_id' => $otherEmployee->id,
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'payload' => ['check_in_at' => '08:05'], 'reason' => 'Quên chấm công', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staffUser)->put(route('staff-requests.update', $sr), [
            'work_date' => now()->toDateString(), 'check_in_at' => '08:15', 'reason' => 'Sửa lại',
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_with_edit_permission_can_update_approved_request_without_reapplying_attendance(): void
    {
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'edit-staff-requests']));
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $sr = StaffRequest::create([
            'code' => 'ATC-202601-0004', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'payload' => ['check_in_at' => '08:05'], 'reason' => 'Quên chấm công', 'status' => 'approved',
            'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        ]);
        AttendanceLog::create([
            'employee_id' => $this->staffEmployee->id, 'work_date' => $sr->work_date->toDateString(),
            'check_in_at' => now()->setTime(8, 5), 'late_minutes' => 5,
        ]);

        $response = $this->actingAs($this->manager)->put(route('staff-requests.update', $sr), [
            'work_date' => $sr->work_date->toDateString(), 'check_in_at' => '08:00', 'reason' => 'Sửa lại cho đúng',
        ]);

        $response->assertRedirect();
        $sr->refresh();
        $this->assertEquals(['check_in_at' => '08:00'], $sr->payload);
        $this->assertEquals('approved', $sr->status);
        // Sửa phiếu đã duyệt KHÔNG tự động áp lại vào AttendanceLog đã ghi nhận trước đó.
        $this->assertDatabaseHas('attendance_logs', ['employee_id' => $this->staffEmployee->id, 'late_minutes' => 5]);
    }

    public function test_update_requires_at_least_one_attendance_correction_time_field(): void
    {
        $sr = StaffRequest::create([
            'code' => 'ATC-202601-0005', 'employee_id' => $this->staffEmployee->id,
            'type' => 'attendance_correction', 'work_date' => now()->toDateString(),
            'payload' => ['check_in_at' => '08:05'], 'reason' => 'Quên chấm công', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staffUser)->put(route('staff-requests.update', $sr), [
            'work_date' => now()->toDateString(), 'reason' => 'Sửa lại',
        ]);

        $response->assertSessionHasErrors(['check_in_at']);
    }
}
