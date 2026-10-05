<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MyAttendanceLogsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;
    private Employee $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'staff']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-own-attendance']));

        $this->user = User::factory()->create();
        $this->user->assignRole('staff');

        $this->employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'user_id' => $this->user->id, 'is_active' => true,
        ]);

        $this->otherEmployee = Employee::create([
            'code' => 'EMP-02', 'name' => 'Trần Thị B', 'is_active' => true,
        ]);
    }

    public function test_employee_can_view_own_attendance_history(): void
    {
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'work_date'   => now()->toDateString(),
            'check_in_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('my-attendance-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('Lịch sử chấm công');
    }

    public function test_employee_only_sees_own_logs_not_others(): void
    {
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'work_date'   => now()->toDateString(),
            'check_in_at' => now(),
        ]);

        AttendanceLog::create([
            'employee_id' => $this->otherEmployee->id,
            'work_date'   => now()->toDateString(),
            'check_in_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('my-attendance-logs.index'));

        $response->assertStatus(200);
        $response->assertViewHas('logs', function ($logs) {
            return $logs->total() === 1 && $logs->first()->employee_id === $this->employee->id;
        });
    }

    public function test_date_filter_narrows_results(): void
    {
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'work_date'   => now()->toDateString(),
            'check_in_at' => now(),
        ]);

        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'work_date'   => now()->subMonth()->toDateString(),
            'check_in_at' => now()->subMonth(),
        ]);

        $response = $this->actingAs($this->user)->get(route('my-attendance-logs.index', [
            'date_from' => now()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('logs', fn($logs) => $logs->total() === 1);
    }

    public function test_forbidden_without_employee_record(): void
    {
        $noEmpUser = User::factory()->create();
        $noEmpUser->assignRole('staff');

        $response = $this->actingAs($noEmpUser)->get(route('my-attendance-logs.index'));
        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_view(): void
    {
        $noPermUser = User::factory()->create();
        $response = $this->actingAs($noPermUser)->get(route('my-attendance-logs.index'));
        $response->assertStatus(403);
    }

    public function test_guest_redirected_to_login(): void
    {
        $response = $this->get(route('my-attendance-logs.index'));
        $response->assertRedirect(route('login'));
    }
}
