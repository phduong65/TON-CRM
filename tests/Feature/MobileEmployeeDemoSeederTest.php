<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MonthlyEmployeeScore;
use App\Models\Notification;
use App\Models\Penalty;
use App\Models\Reward;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Models\StaffRequest;
use App\Models\User;
use Database\Seeders\MobileEmployeeDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileEmployeeDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'code' => 'DEMO',
            'name' => 'Chi nhánh Demo',
            'is_active' => true,
        ]);

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole($adminRole);

        $employeeUser = User::factory()->create(['status' => 'active']);
        $this->employee = Employee::create([
            'user_id' => $employeeUser->id,
            'code' => 'NV-NDMHIEN',
            'name' => 'Nguyễn Đình Minh Hiền',
            'email' => $employeeUser->email,
            'branch_id' => $branch->id,
            'is_active' => true,
            'employment_type' => 'full_time',
            'is_office' => true,
        ]);

        $colleagueUser = User::factory()->create(['status' => 'active']);
        Employee::create([
            'user_id' => $colleagueUser->id,
            'code' => 'NV-DEMO-COLLEAGUE',
            'name' => 'Đồng nghiệp Demo',
            'email' => $colleagueUser->email,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    public function test_seeder_creates_complete_mobile_demo_data_idempotently(): void
    {
        $this->seed(MobileEmployeeDemoSeeder::class);

        $scheduleCount = ShiftSchedule::where('employee_id', $this->employee->id)->count();
        $attendanceCount = AttendanceLog::where('employee_id', $this->employee->id)->count();

        $this->assertGreaterThanOrEqual(35, $scheduleCount);
        $this->assertGreaterThanOrEqual(15, $attendanceCount);
        $this->assertSame(6, MonthlyEmployeeScore::where('employee_id', $this->employee->id)->count());
        $this->assertSame(3, Penalty::where('employee_id', $this->employee->id)->count());
        $this->assertSame(3, Reward::where('employee_id', $this->employee->id)->count());
        $this->assertSame(3, LeaveRequest::where('employee_id', $this->employee->id)->count());
        $this->assertSame(
            2,
            ShiftSwapRequest::where('requester_employee_id', $this->employee->id)->count()
        );
        $this->assertSame(5, StaffRequest::where('employee_id', $this->employee->id)->count());
        $this->assertSame(6, Notification::where('user_id', $this->employee->user_id)->count());

        $todaySchedule = ShiftSchedule::with('shift')
            ->where('employee_id', $this->employee->id)
            ->where('work_date', now()->toDateString())
            ->first();

        $this->assertNotNull($todaySchedule);
        $this->assertSame('wfh', $todaySchedule->shift->work_mode);
        $this->assertFalse(
            AttendanceLog::where('employee_id', $this->employee->id)
                ->where('work_date', now()->toDateString())
                ->exists()
        );

        $this->seed(MobileEmployeeDemoSeeder::class);

        $this->assertSame(
            $scheduleCount,
            ShiftSchedule::where('employee_id', $this->employee->id)->count()
        );
        $this->assertSame(
            $attendanceCount,
            AttendanceLog::where('employee_id', $this->employee->id)->count()
        );
        $this->assertSame(3, Penalty::where('employee_id', $this->employee->id)->count());
        $this->assertSame(3, Reward::where('employee_id', $this->employee->id)->count());
        $this->assertSame(3, LeaveRequest::where('employee_id', $this->employee->id)->count());
        $this->assertSame(5, StaffRequest::where('employee_id', $this->employee->id)->count());
        $this->assertSame(6, Notification::where('user_id', $this->employee->user_id)->count());
    }
}
