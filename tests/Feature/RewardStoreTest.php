<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\RewardMember;
use App\Models\RewardType;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RewardStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;
    private RewardType $rewardType;
    private Branch $branch;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'default_score_per_month', 'value' => 100]);
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'create-rewards']);
        $role = Role::firstOrCreate(['name' => 'manager']);
        $role->givePermissionTo($perm);

        $this->creator = User::factory()->create();
        $this->creator->assignRole('manager');

        $this->branch = Branch::create(['code' => 'CNA', 'name' => 'Chi nhánh A', 'is_active' => true]);
        $this->team   = Team::create(['code' => 'BAR', 'name' => 'Bar', 'branch_id' => $this->branch->id, 'is_active' => true]);

        $category = RewardCategory::create([
            'name'       => 'Khen thưởng',
            'is_active'  => true,
            'created_by' => $this->creator->id,
        ]);

        $this->rewardType = RewardType::create([
            'reward_category_id' => $category->id,
            'name'               => 'Nhân viên xuất sắc',
            'default_points'     => 20,
            'is_active'          => true,
            'created_by'         => $this->creator->id,
        ]);
    }

    private function makeEmployee(string $code, ?int $branchId = null, ?int $teamId = null): Employee
    {
        return Employee::create([
            'code'      => $code,
            'name'      => 'NV ' . $code,
            'branch_id' => $branchId,
            'team_id'   => $teamId,
            'is_active' => true,
        ]);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'target_type'          => 'individual',
            'reward_type_id'       => $this->rewardType->id,
            'total_points_awarded' => 20,
            'description'          => null,
        ], $overrides);
    }

    // ── Tab: Cá nhân (individual) ────────────────────────────────────────────

    public function test_individual_reward_without_extra_members_creates_no_reward_members(): void
    {
        $emp = $this->makeEmployee('EMP-001');

        $this->actingAs($this->creator)
            ->post(route('rewards.store'), $this->basePayload([
                'employee_id' => $emp->id,
                // no members[] → bug: trước đây tạo reward_member cho TẤT CẢ
            ]));

        $this->assertDatabaseCount('reward_members', 0);
    }

    public function test_individual_reward_without_extra_members_does_not_touch_other_employees(): void
    {
        $main  = $this->makeEmployee('EMP-001');
        $other = $this->makeEmployee('EMP-002');

        $this->actingAs($this->creator)
            ->post(route('rewards.store'), $this->basePayload([
                'employee_id' => $main->id,
            ]));

        $this->assertDatabaseMissing('reward_members', ['employee_id' => $other->id]);
    }

    public function test_individual_reward_stores_employee_id_on_reward_record(): void
    {
        $emp = $this->makeEmployee('EMP-001');

        $this->actingAs($this->creator)
            ->post(route('rewards.store'), $this->basePayload([
                'employee_id' => $emp->id,
            ]));

        $this->assertDatabaseHas('rewards', [
            'employee_id' => $emp->id,
            'target_type' => 'individual',
            'status'      => 'pending',
        ]);
    }

    public function test_individual_reward_with_extra_members_creates_only_those_members(): void
    {
        $main   = $this->makeEmployee('EMP-001');
        $member = $this->makeEmployee('EMP-002');
        $other  = $this->makeEmployee('EMP-003');

        $this->actingAs($this->creator)
            ->post(route('rewards.store'), $this->basePayload([
                'employee_id' => $main->id,
                'members'     => [
                    ['employee_id' => $member->id, 'points_awarded' => 10, 'note' => null],
                ],
            ]));

        $this->assertDatabaseCount('reward_members', 1);
        $this->assertDatabaseHas('reward_members', ['employee_id' => $member->id, 'points_awarded' => 10]);
        $this->assertDatabaseMissing('reward_members', ['employee_id' => $other->id]);
    }

    // ── Tab: Chi nhánh (branch) ──────────────────────────────────────────────

    public function test_branch_reward_creates_members_only_for_that_branch(): void
    {
        $branchB = Branch::create(['code' => 'CNB', 'name' => 'Chi nhánh B', 'is_active' => true]);

        $inBranch1 = $this->makeEmployee('EMP-B1', $this->branch->id);
        $inBranch2 = $this->makeEmployee('EMP-B2', $this->branch->id);
        $otherBranch = $this->makeEmployee('EMP-B3', $branchB->id);
        $noBranch    = $this->makeEmployee('EMP-B4');

        $this->actingAs($this->creator)
            ->post(route('rewards.store'), $this->basePayload([
                'target_type'          => 'branch',
                'target_id'            => $this->branch->id,
                'total_points_awarded' => 15,
            ]));

        $reward = Reward::first();

        $this->assertDatabaseHas('reward_members', ['reward_id' => $reward->id, 'employee_id' => $inBranch1->id]);
        $this->assertDatabaseHas('reward_members', ['reward_id' => $reward->id, 'employee_id' => $inBranch2->id]);
        $this->assertDatabaseMissing('reward_members', ['reward_id' => $reward->id, 'employee_id' => $otherBranch->id]);
        $this->assertDatabaseMissing('reward_members', ['reward_id' => $reward->id, 'employee_id' => $noBranch->id]);
    }

    public function test_branch_reward_sets_correct_points_per_member(): void
    {
        $emp = $this->makeEmployee('EMP-B1', $this->branch->id);

        $this->actingAs($this->creator)
            ->post(route('rewards.store'), $this->basePayload([
                'target_type'          => 'branch',
                'target_id'            => $this->branch->id,
                'total_points_awarded' => 25,
            ]));

        $this->assertDatabaseHas('reward_members', [
            'employee_id'    => $emp->id,
            'points_awarded' => 25,
        ]);
    }

    // ── Tab: Đội nhóm (team) ────────────────────────────────────────────────

    public function test_team_reward_creates_members_only_for_that_team(): void
    {
        $teamB = Team::create(['code' => 'KIT', 'name' => 'Kitchen', 'branch_id' => $this->branch->id, 'is_active' => true]);

        $inTeam    = $this->makeEmployee('EMP-T1', $this->branch->id, $this->team->id);
        $otherTeam = $this->makeEmployee('EMP-T2', $this->branch->id, $teamB->id);
        $noTeam    = $this->makeEmployee('EMP-T3', $this->branch->id);

        $this->actingAs($this->creator)
            ->post(route('rewards.store'), $this->basePayload([
                'target_type'          => 'team',
                'target_id'            => $this->team->id,
                'total_points_awarded' => 10,
            ]));

        $reward = Reward::first();

        $this->assertDatabaseHas('reward_members', ['reward_id' => $reward->id, 'employee_id' => $inTeam->id]);
        $this->assertDatabaseMissing('reward_members', ['reward_id' => $reward->id, 'employee_id' => $otherTeam->id]);
        $this->assertDatabaseMissing('reward_members', ['reward_id' => $reward->id, 'employee_id' => $noTeam->id]);
    }

    // ── Tab: Tất cả (all) ───────────────────────────────────────────────────

    public function test_all_reward_creates_members_for_every_active_employee(): void
    {
        $emp1     = $this->makeEmployee('EMP-A1', $this->branch->id);
        $emp2     = $this->makeEmployee('EMP-A2');
        $inactive = Employee::create(['code' => 'EMP-A3', 'name' => 'NV A3', 'is_active' => false]);

        $this->actingAs($this->creator)
            ->post(route('rewards.store'), $this->basePayload([
                'target_type'          => 'all',
                'total_points_awarded' => 5,
            ]));

        $reward = Reward::first();

        $this->assertDatabaseHas('reward_members', ['reward_id' => $reward->id, 'employee_id' => $emp1->id]);
        $this->assertDatabaseHas('reward_members', ['reward_id' => $reward->id, 'employee_id' => $emp2->id]);
        $this->assertDatabaseMissing('reward_members', ['reward_id' => $reward->id, 'employee_id' => $inactive->id]);
    }

    // ── Authorization ────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_create_reward(): void
    {
        $emp = $this->makeEmployee('EMP-001');

        $response = $this->post(route('rewards.store'), $this->basePayload(['employee_id' => $emp->id]));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('rewards', 0);
    }

    public function test_user_without_permission_cannot_create_reward(): void
    {
        $emp        = $this->makeEmployee('EMP-001');
        $noPermUser = User::factory()->create();

        $response = $this->actingAs($noPermUser)
            ->post(route('rewards.store'), $this->basePayload(['employee_id' => $emp->id]));

        $response->assertStatus(403);
        $this->assertDatabaseCount('rewards', 0);
    }
}
