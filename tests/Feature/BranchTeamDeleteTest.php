<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchTeamDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        foreach (['view-branches', 'create-branches', 'edit-branches', 'delete-branches',
                  'view-teams', 'create-teams', 'edit-teams', 'delete-teams'] as $perm) {
            $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
    }

    public function test_deactivate_branch_keeps_record_and_sets_inactive(): void
    {
        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->actingAs($this->adminUser)
            ->delete(route('branches.destroy', $branch))
            ->assertRedirect();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => false, 'deleted_at' => null]);
    }

    public function test_permanent_delete_blocked_when_branch_has_active_team(): void
    {
        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        Team::create(['code' => 'TEAM-1', 'name' => 'Team A', 'branch_id' => $branch->id, 'is_active' => true]);

        $this->actingAs($this->adminUser)
            ->delete(route('branches.destroy', $branch), ['_delete_type' => 'permanent'])
            ->assertRedirect();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'deleted_at' => null]);
    }

    public function test_permanent_delete_succeeds_when_branch_has_no_active_children(): void
    {
        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->actingAs($this->adminUser)
            ->delete(route('branches.destroy', $branch), ['_delete_type' => 'permanent'])
            ->assertRedirect();

        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
    }

    public function test_branch_code_can_be_reused_after_permanent_delete(): void
    {
        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->actingAs($this->adminUser)
            ->delete(route('branches.destroy', $branch), ['_delete_type' => 'permanent']);

        $response = $this->actingAs($this->adminUser)->post(route('branches.store'), [
            'code' => 'BR-1', 'name' => 'Chi nhánh 1 (mới)',
        ]);

        $response->assertSessionDoesntHaveErrors(['code']);
        $this->assertDatabaseHas('branches', ['code' => 'BR-1', 'deleted_at' => null]);
    }

    public function test_permanent_delete_blocked_when_team_has_active_employee(): void
    {
        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        $team = Team::create(['code' => 'TEAM-1', 'name' => 'Team A', 'branch_id' => $branch->id, 'is_active' => true]);
        Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'branch_id' => $branch->id, 'team_id' => $team->id,
            'is_active' => true, 'employment_type' => 'full_time', 'is_office' => true,
        ]);

        $this->actingAs($this->adminUser)
            ->delete(route('teams.destroy', $team), ['_delete_type' => 'permanent'])
            ->assertRedirect();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'deleted_at' => null]);
    }

    public function test_permanent_delete_succeeds_when_team_has_no_active_employee(): void
    {
        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        $team = Team::create(['code' => 'TEAM-1', 'name' => 'Team A', 'branch_id' => $branch->id, 'is_active' => true]);

        $this->actingAs($this->adminUser)
            ->delete(route('teams.destroy', $team), ['_delete_type' => 'permanent'])
            ->assertRedirect();

        $this->assertSoftDeleted('teams', ['id' => $team->id]);
    }
}
