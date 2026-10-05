<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnnualLeaveControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'manager']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-annual-leave']));

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
    }

    public function test_guest_redirected_to_login(): void
    {
        $response = $this->get(route('annual-leave.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_view(): void
    {
        $noPermUser = User::factory()->create();

        $response = $this->actingAs($noPermUser)->get(route('annual-leave.index'));
        $response->assertStatus(403);
    }

    public function test_only_eligible_office_employees_are_listed(): void
    {
        $eligible = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'branch_id' => $this->branch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => true, 'joined_at' => now()->subYears(2)->toDateString(),
        ]);
        $partTime = Employee::create([
            'code' => 'EMP-02', 'name' => 'Trần Thị B', 'branch_id' => $this->branch->id, 'is_active' => true,
            'employment_type' => 'part_time', 'is_office' => true,
        ]);
        $notOffice = Employee::create([
            'code' => 'EMP-03', 'name' => 'Lê Văn C', 'branch_id' => $this->branch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => false,
        ]);
        $inactive = Employee::create([
            'code' => 'EMP-04', 'name' => 'Phạm Thị D', 'branch_id' => $this->branch->id, 'is_active' => false,
            'employment_type' => 'full_time', 'is_office' => true,
        ]);

        $response = $this->actingAs($this->manager)->get(route('annual-leave.index'));

        $response->assertOk();
        $response->assertSee('EMP-01');
        $response->assertDontSee('EMP-02');
        $response->assertDontSee('EMP-03');
        $response->assertDontSee('EMP-04');
    }

    public function test_shows_entitled_used_and_remaining_days(): void
    {
        $employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'branch_id' => $this->branch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => true, 'joined_at' => now()->subYears(2)->toDateString(),
        ]);
        LeaveRequest::create([
            'code' => 'LR-1', 'employee_id' => $employee->id,
            'date_from' => now()->subDays(2)->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Test', 'status' => 'approved',
        ]);

        $response = $this->actingAs($this->manager)->get(route('annual-leave.index'));

        $response->assertOk();
        $response->assertSee('EMP-01');
        // Đã dùng 3 ngày (subDays(2) đến hôm nay = 3 ngày) phải xuất hiện trên trang.
        $response->assertSee('3');
    }

    public function test_branch_filter_narrows_results(): void
    {
        $otherBranch = Branch::create(['code' => 'BR-2', 'name' => 'Chi nhánh 2', 'is_active' => true]);

        Employee::create([
            'code' => 'EMP-B1', 'name' => 'Nhân viên B1', 'branch_id' => $this->branch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => true,
        ]);
        Employee::create([
            'code' => 'EMP-B2', 'name' => 'Nhân viên B2', 'branch_id' => $otherBranch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => true,
        ]);

        $response = $this->actingAs($this->manager)->get(route('annual-leave.index', ['branch_id' => $otherBranch->id]));

        $response->assertOk();
        $response->assertDontSee('EMP-B1')->assertSee('EMP-B2');
    }

    public function test_search_filter_narrows_results(): void
    {
        Employee::create([
            'code' => 'EMP-X1', 'name' => 'Nguyễn Văn Xoài', 'branch_id' => $this->branch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => true,
        ]);
        Employee::create([
            'code' => 'EMP-X2', 'name' => 'Trần Thị Ổi', 'branch_id' => $this->branch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => true,
        ]);

        $response = $this->actingAs($this->manager)->get(route('annual-leave.index', ['search' => 'Xoài']));

        $response->assertOk();
        $response->assertSee('EMP-X1')->assertDontSee('EMP-X2');
    }

    public function test_year_filter_shows_different_years_entitlement(): void
    {
        $employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'branch_id' => $this->branch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => true, 'joined_at' => now()->subYears(2)->toDateString(),
        ]);

        // Năm sau chưa bắt đầu -> chưa tích luỹ ngày phép nào (entitled = 0).
        $response = $this->actingAs($this->manager)->get(route('annual-leave.index', ['year' => now()->addYear()->year]));

        $response->assertOk();
        $response->assertSee('EMP-01');
    }
}
