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

/**
 * Trang Thưởng điểm sau redesign + lỗi cập nhật phiếu (trước đây modal Sửa xoá sạch danh sách người
 * nhận và gán employee_id cho cả phiếu tập thể).
 */
class RewardPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private RewardType $rewardType;
    private Branch $branch;
    private Employee $empA;
    private Employee $empB;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'default_score_per_month', 'value' => 100]);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['view-rewards', 'create-rewards', 'approve-rewards', 'revoke-rewards'] as $p) {
            Role::firstOrCreate(['name' => 'manager'])->givePermissionTo(Permission::firstOrCreate(['name' => $p]));
        }
        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->branch = Branch::create(['code' => 'CNA', 'name' => 'Chi nhánh A', 'is_active' => true]);
        $team = Team::create(['code' => 'BAR', 'name' => 'Bar', 'branch_id' => $this->branch->id, 'is_active' => true]);
        $this->empA = Employee::create(['code' => 'A1', 'name' => 'NV A', 'branch_id' => $this->branch->id, 'team_id' => $team->id, 'is_active' => true]);
        $this->empB = Employee::create(['code' => 'B1', 'name' => 'NV B', 'branch_id' => $this->branch->id, 'team_id' => $team->id, 'is_active' => true]);

        $category = RewardCategory::create(['name' => 'Khen thưởng', 'is_active' => true, 'created_by' => $this->manager->id]);
        $this->rewardType = RewardType::create([
            'reward_category_id' => $category->id, 'name' => 'Xuất sắc', 'default_points' => 10,
            'is_active' => true, 'created_by' => $this->manager->id,
        ]);
    }

    private function reward(array $attrs = []): Reward
    {
        return Reward::create(array_merge([
            'code' => 'RWD-' . uniqid(), 'target_type' => 'individual', 'reward_type_id' => $this->rewardType->id,
            'employee_id' => $this->empA->id, 'total_points_awarded' => 10, 'status' => 'pending',
            'created_by' => $this->manager->id,
        ], $attrs));
    }

    private function branchReward(): Reward
    {
        $reward = $this->reward(['target_type' => 'branch', 'target_id' => $this->branch->id, 'employee_id' => null]);
        foreach ([$this->empA, $this->empB] as $emp) {
            RewardMember::create(['reward_id' => $reward->id, 'employee_id' => $emp->id, 'points_awarded' => 10]);
        }
        return $reward;
    }

    public function test_updating_branch_reward_keeps_recipients_and_applies_new_points(): void
    {
        $reward = $this->branchReward();

        $this->actingAs($this->manager)->put(route('rewards.update', $reward), [
            'reward_type_id' => $this->rewardType->id, 'total_points_awarded' => 15, 'description' => 'Sửa',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $reward->refresh();
        $this->assertNull($reward->employee_id);
        $this->assertEquals(15, $reward->total_points_awarded);
        $this->assertEquals([15, 15], $reward->members()->pluck('points_awarded')->all());
    }

    public function test_updating_individual_reward_without_sync_flag_keeps_extra_members(): void
    {
        $reward = $this->reward();
        RewardMember::create(['reward_id' => $reward->id, 'employee_id' => $this->empB->id, 'points_awarded' => 5]);

        $this->actingAs($this->manager)->put(route('rewards.update', $reward), [
            'reward_type_id' => $this->rewardType->id, 'employee_id' => $this->empA->id, 'total_points_awarded' => 12,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(1, $reward->members()->count());
        $this->assertEquals(12, $reward->fresh()->total_points_awarded);
    }

    public function test_updating_individual_reward_requires_employee(): void
    {
        $reward = $this->reward();

        $this->actingAs($this->manager)->put(route('rewards.update', $reward), [
            'reward_type_id' => $this->rewardType->id, 'total_points_awarded' => 12,
        ])->assertSessionHasErrors('employee_id');
    }

    public function test_index_shows_status_tabs_with_counts_and_branch_target_name(): void
    {
        $this->reward();
        $this->branchReward();
        $this->reward(['status' => 'approved']);

        $response = $this->actingAs($this->manager)->get(route('rewards.index'));

        $response->assertOk();
        $html = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertMatchesRegularExpression('/Tất cả <span class="status-tab-count">3<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Chờ duyệt <span class="status-tab-count">2<\/span>/', $html);
        $response->assertSee('Chi nhánh A');
        $response->assertSee('2 người nhận');
    }

    public function test_show_branch_reward_lists_recipients_and_workflow_actions(): void
    {
        $reward = $this->branchReward();

        $response = $this->actingAs($this->manager)->get(route('rewards.show', $reward));

        $response->assertOk();
        $response->assertSee('Chi nhánh Chi nhánh A');
        $response->assertSee('NV A');
        $response->assertSee('NV B');
        $response->assertSee('id="approveRewardModal"', false);
        $response->assertSee('id="editRewardModal"', false);
        $response->assertDontSee('name="employee_id"', false); // phiếu tập thể không chọn nhân viên
    }

    public function test_show_hides_workflow_actions_without_permission(): void
    {
        $viewer = User::factory()->create();
        Role::firstOrCreate(['name' => 'viewer'])->givePermissionTo('view-rewards');
        $viewer->assignRole('viewer');
        $reward = $this->reward();

        $response = $this->actingAs($viewer)->get(route('rewards.show', $reward));

        $response->assertOk();
        $response->assertDontSee('id="approveRewardModal"', false);
        $response->assertDontSee('id="editRewardModal"', false);
    }
}
