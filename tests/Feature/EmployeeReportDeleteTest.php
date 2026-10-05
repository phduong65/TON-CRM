<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeReportDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $reporterUser;
    private Employee $reporterEmployee;
    private User $otherUser;
    private Employee $reportedEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        foreach (['view-reports', 'create-reports'] as $perm) {
            $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->reporterUser = User::factory()->create();
        $this->reporterUser->assignRole('staff');
        $this->otherUser = User::factory()->create();
        $this->otherUser->assignRole('staff');

        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->reporterEmployee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'user_id' => $this->reporterUser->id,
            'branch_id' => $branch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => true,
        ]);

        $this->reportedEmployee = Employee::create([
            'code' => 'EMP-02', 'name' => 'Trần Văn B', 'user_id' => $this->otherUser->id,
            'branch_id' => $branch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => true,
        ]);
    }

    private function makeReport(array $overrides = []): EmployeeReport
    {
        return EmployeeReport::create(array_merge([
            'code'                 => 'RPT-TEST-0001',
            'reporter_employee_id' => $this->reporterEmployee->id,
            'reported_employee_id' => $this->reportedEmployee->id,
            'type'                 => 'individual',
            'description'          => 'Vi phạm nội quy',
            'status'               => 'pending',
            'reward_points'        => 5,
            'created_by'           => $this->reporterUser->id,
        ], $overrides));
    }

    public function test_creator_can_delete_own_pending_report(): void
    {
        $report = $this->makeReport();

        $response = $this->actingAs($this->reporterUser)->delete(route('reports.destroy', $report));

        $response->assertRedirect();
        $this->assertSoftDeleted('employee_reports', ['id' => $report->id]);
    }

    public function test_non_creator_cannot_delete_report(): void
    {
        $report = $this->makeReport();

        $response = $this->actingAs($this->otherUser)->delete(route('reports.destroy', $report));

        $response->assertForbidden();
        $this->assertDatabaseHas('employee_reports', ['id' => $report->id, 'deleted_at' => null]);
    }

    public function test_approved_report_cannot_be_deleted(): void
    {
        $report = $this->makeReport(['status' => 'approved']);

        $response = $this->actingAs($this->reporterUser)->delete(route('reports.destroy', $report));

        $response->assertForbidden();
        $this->assertDatabaseHas('employee_reports', ['id' => $report->id, 'deleted_at' => null]);
    }

    /**
     * EmployeeReport dùng SoftDeletes, và EmployeeReportsController::store() đã dùng withTrashed()
     * khi đếm để sinh code — verify thực sự không trùng "code" sau khi xoá mềm 1 báo cáo ở giữa
     * tháng (cùng lớp lỗi vừa fix ở StaffRequest/LeaveRequest/ShiftSwapRequest/Penalty).
     */
    public function test_creating_report_after_soft_delete_does_not_collide_on_code(): void
    {
        // Mỗi lần tạo dùng description khác nhau — không phải double-submit thật (đã có guard chặn
        // riêng, xem test_double_submit_creates_only_one_report), chỉ để có 3 bản ghi phân biệt.
        $payload = fn(string $desc) => [
            'type' => 'individual', 'reported_employee_id' => $this->reportedEmployee->id,
            'description' => $desc,
        ];

        $this->actingAs($this->reporterUser)->post(route('reports.store'), $payload('Vi phạm a'));
        $this->actingAs($this->reporterUser)->post(route('reports.store'), $payload('Vi phạm b'));
        $this->actingAs($this->reporterUser)->post(route('reports.store'), $payload('Vi phạm c'));

        EmployeeReport::orderBy('id')->skip(1)->first()->delete(); // soft-delete báo cáo thứ 2

        $response = $this->actingAs($this->reporterUser)->post(route('reports.store'), $payload('Vi phạm d'));

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('employee_reports', 4); // 3 tạo đầu (1 đã xoá mềm) + 1 vừa tạo
        $codes = EmployeeReport::withTrashed()->pluck('code');
        $this->assertEquals($codes->count(), $codes->unique()->count(), 'Code bị trùng giữa các bản ghi.');
    }

    /**
     * Double-click / gửi lại form khi mạng chậm không được tạo 2 báo cáo giống hệt nhau.
     * Xem PreventsDuplicateSubmission::wasJustSubmitted() và EmployeeReportsController::store().
     */
    public function test_double_submit_creates_only_one_report(): void
    {
        $payload = [
            'type' => 'individual', 'reported_employee_id' => $this->reportedEmployee->id,
            'description' => 'Vi phạm nội quy',
        ];

        $this->actingAs($this->reporterUser)->post(route('reports.store'), $payload)->assertRedirect();
        $this->actingAs($this->reporterUser)->post(route('reports.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('employee_reports', 1);
    }
}
