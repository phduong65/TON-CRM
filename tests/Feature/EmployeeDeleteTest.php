<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        foreach (['view-employees', 'create-employees', 'edit-employees', 'delete-employees'] as $perm) {
            $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
    }

    public function test_employee_code_and_email_can_be_reused_after_permanent_delete(): void
    {
        $target = User::factory()->create();
        $employee = Employee::create([
            'code' => 'EMP-99', 'name' => 'Nguyễn Văn A', 'email' => 'a@example.com',
            'user_id' => $target->id, 'branch_id' => $this->branch->id, 'is_active' => true,
            'employment_type' => 'full_time', 'is_office' => true,
        ]);

        $this->actingAs($this->adminUser)
            ->delete(route('employees.destroy', $employee), ['_delete_type' => 'permanent'])
            ->assertRedirect();

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);

        // Reusing the same code/email must pass validation and actually persist —
        // this was blocked before the deleted_at-aware unique constraint fix, since the
        // "permanently deleted" employee row still physically exists (soft delete only).
        $response = $this->actingAs($this->adminUser)->post(route('employees.store'), [
            'code'            => 'EMP-99',
            'name'            => 'Lê Văn C',
            'email'           => 'a@example.com',
            'branch_id'       => $this->branch->id,
            'team_id'         => $this->makeTeamId(),
            'employment_type' => 'full_time',
            'is_office'       => 1,
        ]);

        $response->assertSessionDoesntHaveErrors(['code', 'email']);
        $this->assertDatabaseHas('employees', ['code' => 'EMP-99', 'email' => 'a@example.com', 'deleted_at' => null]);
    }

    private function makeTeamId(): int
    {
        return \App\Models\Team::create([
            'code' => 'TEAM-A', 'name' => 'Team A', 'branch_id' => $this->branch->id, 'is_active' => true, 'type' => 'service',
        ])->id;
    }
}
