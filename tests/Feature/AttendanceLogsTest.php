<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceLogsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Employee $employeeA;
    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'manager']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-attendance']));

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->employeeA = Employee::create(['code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'branch_id' => $branch->id, 'is_active' => true]);
        $this->employeeB = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'branch_id' => $branch->id, 'is_active' => true]);

        AttendanceLog::create(['employee_id' => $this->employeeA->id, 'work_date' => now()->toDateString(), 'check_in_at' => now()]);
        AttendanceLog::create(['employee_id' => $this->employeeB->id, 'work_date' => now()->toDateString(), 'check_in_at' => now()]);
    }

    public function test_manager_can_view_attendance_logs_with_employee_combobox(): void
    {
        $response = $this->actingAs($this->manager)->get(route('attendance-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('Nguyễn Văn A');
        $response->assertSee('Trần Thị B');
        $response->assertSee('data-employee-combobox', false);
    }

    public function test_filtering_by_employee_id_narrows_results(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('attendance-logs.index', ['employee_id' => $this->employeeA->id]));

        $response->assertStatus(200);
        $response->assertSee('Nguyễn Văn A');
        $response->assertDontSee('Trần Thị B');
    }

    public function test_user_without_permission_cannot_view_attendance_logs(): void
    {
        $noPermUser = User::factory()->create();
        $response = $this->actingAs($noPermUser)->get(route('attendance-logs.index'));
        $response->assertStatus(403);
    }

    // ── Xuất Excel ───────────────────────────────────────────────────────

    public function test_manager_can_export_attendance_logs(): void
    {
        $role = Role::where('name', 'manager')->first();
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'export-attendance']));

        $response = $this->actingAs($this->manager)->get(route('attendance-logs.export', [
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_user_without_export_permission_cannot_export_attendance_logs(): void
    {
        $response = $this->actingAs($this->manager)->get(route('attendance-logs.export'));
        $response->assertStatus(403);
    }
}
