<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeScore;
use App\Models\MonthlyEmployeeScore;
use App\Models\Penalty;
use App\Models\Team;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeePerformanceProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        foreach (['view-employees', 'approve-penalties', 'view-penalties'] as $perm) {
            $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $branch = Branch::create(['code' => 'BR-TEST', 'name' => 'Chi nhánh Test', 'is_active' => true]);
        $team = Team::create(['branch_id' => $branch->id, 'code' => 'TEAM-SRV', 'name' => 'Service', 'is_active' => true]);

        $user = User::factory()->create();
        $this->employee = Employee::create([
            'user_id'     => $user->id,
            'branch_id'   => $branch->id,
            'team_id'     => $team->id,
            'code'        => 'NV-TEST01',
            'name'        => 'Nguyễn Tường Vy',
            'email'       => 'tuongvy@example.com',
            'phone'       => '0901234567',
            'is_active'   => true,
        ]);

        EmployeeScore::create([
            'employee_id' => $this->employee->id,
            'points'      => 100,
            'reason'      => 'Khởi tạo điểm đầu tháng',
        ]);
        EmployeeScore::create([
            'employee_id' => $this->employee->id,
            'points'      => -1,
            'reason'      => 'Đi trễ 5 phút',
        ]);
    }

    public function test_employee_show_page_displays_performance_overview(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('employees.show', $this->employee));

        $response->assertOk();
        $response->assertSee('Nguyễn Tường Vy');
        $response->assertSee('NV-TEST01');
        $response->assertSee('99');
        $response->assertSee('/ 100');
        $response->assertSee('Lịch sử xử phạt');
        $response->assertSee('Lịch sử điểm thưởng/phạt');
    }

    public function test_employee_performance_calculates_monthly_trend(): void
    {
        $prevMonth = now()->subMonth();
        MonthlyEmployeeScore::create([
            'employee_id'     => $this->employee->id,
            'month'           => $prevMonth->month,
            'year'            => $prevMonth->year,
            'initial_score'   => 100,
            'deducted_points' => 4,
            'rewarded_points' => 0,
            'final_score'     => 96,
            'zone'            => 'green',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('employees.show', $this->employee));

        $response->assertOk();
        // Current total score = 99 (100 - 1). Previous final_score = 96. Trend = +3
        $response->assertSee('+3 tháng này');
    }

    public function test_pending_penalty_shows_actions_for_privileged_users(): void
    {
        $violation = Violation::create([
            'name'             => 'Đi trễ 15-30p',
            'severity'         => 'low',
            'points_deducted'  => 5,
            'is_active'        => true,
        ]);

        $penalty = Penalty::create([
            'employee_id'           => $this->employee->id,
            'violation_id'          => $violation->id,
            'created_by'            => $this->adminUser->id,
            'status'                => 'pending',
            'total_points_deducted' => 5,
            'description'           => 'Đi trễ ca sáng',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('employees.show', $this->employee));

        $response->assertOk();
        $response->assertSee('Đi trễ 15-30p');
        $response->assertSee('Duyệt');
        $response->assertSee('Từ chối');
    }
}
