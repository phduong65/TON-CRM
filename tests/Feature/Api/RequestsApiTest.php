<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\StaffRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RequestsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // NotificationService::approverIds() consulta các permission "approve-*" dù nhân viên
        // thường không có — permission record vẫn phải tồn tại trong DB, nếu không Spatie ném lỗi.
        foreach (['approve-leave-requests', 'approve-staff-requests', 'approve-shift-swaps'] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $role = Role::firstOrCreate(['name' => 'staff']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-leave-requests']));
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'create-leave-requests']));
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-staff-requests']));
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'create-staff-requests']));

        $this->user = User::factory()->create(['status' => 'active']);
        $this->user->assignRole('staff');
        $this->token = $this->user->createToken('test')->plainTextToken;

        $this->employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'user_id' => $this->user->id, 'is_active' => true,
        ]);
    }

    private function auth()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_employee_can_submit_leave_request(): void
    {
        $response = $this->auth()->postJson('/api/leave-requests', [
            'date_from' => now()->addDays(3)->toDateString(),
            'date_to'   => now()->addDays(4)->toDateString(),
            'type'      => 'unpaid',
            'reason'    => 'Việc gia đình',
        ]);

        $response->assertStatus(201)->assertJsonPath('status', 'pending');
        $this->assertDatabaseHas('leave_requests', ['employee_id' => $this->employee->id, 'type' => 'unpaid']);
    }

    /**
     * "Nghỉ ốm" (sick) và "Khác" (other) đã bị bỏ khỏi hệ thống — chỉ còn "annual"/"unpaid".
     */
    public function test_sick_and_other_leave_types_are_no_longer_accepted(): void
    {
        foreach (['sick', 'other'] as $type) {
            $response = $this->auth()->postJson('/api/leave-requests', [
                'date_from' => now()->addDays(3)->toDateString(),
                'date_to'   => now()->addDays(4)->toDateString(),
                'type'      => $type,
                'reason'    => 'x',
            ]);

            $response->assertStatus(422);
        }
    }

    public function test_employee_only_sees_own_leave_requests(): void
    {
        $otherUser = User::factory()->create(['status' => 'active']);
        $other = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'user_id' => $otherUser->id, 'is_active' => true]);

        LeaveRequest::create([
            'code' => 'LR-OWN', 'employee_id' => $this->employee->id,
            'date_from' => now(), 'date_to' => now(), 'type' => 'sick', 'reason' => 'x', 'status' => 'pending',
        ]);
        LeaveRequest::create([
            'code' => 'LR-OTHER', 'employee_id' => $other->id,
            'date_from' => now(), 'date_to' => now(), 'type' => 'sick', 'reason' => 'y', 'status' => 'pending',
        ]);

        $response = $this->auth()->getJson('/api/leave-requests');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('LR-OWN'));
        $this->assertFalse($codes->contains('LR-OTHER'));
    }

    public function test_employee_can_cancel_own_pending_leave_request(): void
    {
        $lr = LeaveRequest::create([
            'code' => 'LR-1', 'employee_id' => $this->employee->id,
            'date_from' => now(), 'date_to' => now(), 'type' => 'sick', 'reason' => 'x', 'status' => 'pending',
        ]);

        $this->auth()->deleteJson("/api/leave-requests/{$lr->id}")->assertOk();
        $this->assertSoftDeleted('leave_requests', ['id' => $lr->id]);
    }

    public function test_employee_cannot_cancel_others_leave_request(): void
    {
        $otherUser = User::factory()->create(['status' => 'active']);
        $other = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'user_id' => $otherUser->id, 'is_active' => true]);
        $lr = LeaveRequest::create([
            'code' => 'LR-1', 'employee_id' => $other->id,
            'date_from' => now(), 'date_to' => now(), 'type' => 'sick', 'reason' => 'x', 'status' => 'pending',
        ]);

        $this->auth()->deleteJson("/api/leave-requests/{$lr->id}")->assertStatus(403);
    }

    public function test_employee_can_submit_staff_request_business_trip(): void
    {
        $response = $this->auth()->postJson('/api/staff-requests', [
            'type'      => 'business_trip',
            'work_date' => now()->toDateString(),
            'from_time' => '09:00',
            'to_time'   => '11:00',
            'location'  => 'Gặp khách hàng',
            'reason'    => 'Công tác',
        ]);

        $response->assertStatus(201)->assertJsonPath('type', 'business_trip');
        $this->assertDatabaseHas('staff_requests', ['employee_id' => $this->employee->id, 'type' => 'business_trip']);
    }

    public function test_staff_request_without_employee_record_is_forbidden(): void
    {
        $noEmpUser = User::factory()->create(['status' => 'active']);
        $noEmpUser->assignRole('staff');
        $token = $noEmpUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/staff-requests');

        $response->assertStatus(403);
    }

    /**
     * remainingDays() mặc định tính theo NĂM HIỆN TẠI — xin nghỉ phép năm cho ngày thuộc NĂM SAU
     * (chưa tích luỹ ngày nào) phải bị từ chối dù năm nay còn dư nhiều. Xem
     * Api\LeaveRequestController::store() và LeaveRequestTest::test_annual_leave_for_future_year_is_checked_against_that_years_balance().
     */
    public function test_annual_leave_for_future_year_is_checked_against_that_years_balance(): void
    {
        $this->employee->update([
            'employment_type' => 'full_time', 'is_office' => true,
            'joined_at' => now()->subYears(2)->toDateString(),
        ]);

        $nextYearDate = now()->addYear()->startOfYear()->addDays(5);

        $response = $this->auth()->postJson('/api/leave-requests', [
            'date_from' => $nextYearDate->toDateString(),
            'date_to'   => $nextYearDate->copy()->addDays(2)->toDateString(),
            'type'      => 'annual',
            'reason'    => 'Xin nghỉ đầu năm sau',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('leave_requests', ['employee_id' => $this->employee->id]);
    }
}
