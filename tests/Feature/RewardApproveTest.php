<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MonthlyEmployeeScore;
use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\RewardMember;
use App\Models\RewardType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RewardApproveTest extends TestCase
{
    use RefreshDatabase;

    private User $approver;
    private Employee $employee;
    private RewardType $rewardType;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'default_score_per_month', 'value' => 100]);
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'approve-rewards']);
        $role = Role::firstOrCreate(['name' => 'manager']);
        $role->givePermissionTo($perm);

        $this->approver = User::factory()->create();
        $this->approver->assignRole('manager');

        $this->employee = Employee::create([
            'code'      => 'EMP-001',
            'name'      => 'Nguyễn Văn A',
            'is_active' => true,
        ]);

        $category = RewardCategory::create([
            'name'       => 'Khen thưởng',
            'is_active'  => true,
            'created_by' => $this->approver->id,
        ]);

        $this->rewardType = RewardType::create([
            'reward_category_id' => $category->id,
            'name'               => 'Nhân viên xuất sắc',
            'default_points'     => 20,
            'is_active'          => true,
            'created_by'         => $this->approver->id,
        ]);
    }

    private function makePendingReward(int $points = 20): Reward
    {
        return Reward::create([
            'code'                 => 'REW-' . uniqid(),
            'reward_type_id'       => $this->rewardType->id,
            'employee_id'          => $this->employee->id,
            'status'               => 'pending',
            'total_points_awarded' => $points,
            'created_by'           => $this->approver->id,
        ]);
    }

    // ── Approve workflow ──────────────────────────────────────────────────────

    public function test_pending_reward_can_be_approved(): void
    {
        $reward = $this->makePendingReward();

        $response = $this->actingAs($this->approver)
            ->post(route('rewards.approve', $reward));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('rewards', [
            'id'     => $reward->id,
            'status' => 'approved',
        ]);
    }

    public function test_approved_reward_cannot_be_approved_again(): void
    {
        $reward = $this->makePendingReward();
        $reward->update(['status' => 'approved']);

        $response = $this->actingAs($this->approver)
            ->post(route('rewards.approve', $reward));

        $response->assertStatus(403);
    }

    // ── Score reward ──────────────────────────────────────────────────────────

    public function test_approve_adds_points_to_monthly_score(): void
    {
        $reward = $this->makePendingReward(20);

        $this->actingAs($this->approver)
            ->post(route('rewards.approve', $reward));

        $score = MonthlyEmployeeScore::where('employee_id', $this->employee->id)
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();

        // base score is capped at 100; reward overflow goes to surplus_points
        $this->assertNotNull($score);
        $this->assertEquals(100, $score->final_score);
        $this->assertEquals(20, $score->surplus_points);
        $this->assertEquals(20, $score->rewarded_points);
    }

    public function test_approve_adds_points_to_all_members(): void
    {
        $reward  = $this->makePendingReward(10);
        $member1 = Employee::create(['code' => 'EMP-002', 'name' => 'NV B', 'is_active' => true]);

        RewardMember::create(['reward_id' => $reward->id, 'employee_id' => $member1->id, 'points_awarded' => 5]);

        $this->actingAs($this->approver)
            ->post(route('rewards.approve', $reward));

        $mainScore    = MonthlyEmployeeScore::where('employee_id', $this->employee->id)
            ->where('month', now()->month)->where('year', now()->year)->first();
        $memberScore  = MonthlyEmployeeScore::where('employee_id', $member1->id)
            ->where('month', now()->month)->where('year', now()->year)->first();

        $this->assertEquals(100, $mainScore->final_score);
        $this->assertEquals(10, $mainScore->surplus_points);

        $this->assertEquals(100, $memberScore->final_score);
        $this->assertEquals(5, $memberScore->surplus_points);
    }

    // ── Race condition prevention ─────────────────────────────────────────────

    public function test_two_sequential_approvals_only_reward_once(): void
    {
        $reward = $this->makePendingReward(20);

        $this->actingAs($this->approver)->post(route('rewards.approve', $reward));

        $response = $this->actingAs($this->approver)->post(route('rewards.approve', $reward));
        $response->assertStatus(403);

        $score = MonthlyEmployeeScore::where('employee_id', $this->employee->id)
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();

        // Should be rewarded only once: final_score=100 (capped), surplus=20; NOT surplus=40
        $this->assertEquals(100, $score->final_score);
        $this->assertEquals(20, $score->surplus_points);
        $this->assertEquals(20, $score->rewarded_points);
    }

    public function test_cumulative_rewards_are_correct_across_multiple_rewards(): void
    {
        $reward1 = $this->makePendingReward(10);
        $reward2 = $this->makePendingReward(15);

        $this->actingAs($this->approver)->post(route('rewards.approve', $reward1));
        $this->actingAs($this->approver)->post(route('rewards.approve', $reward2));

        $score = MonthlyEmployeeScore::where('employee_id', $this->employee->id)
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();

        // Both rewards overflow into surplus: final_score=100 (capped), surplus=10+15=25
        $this->assertEquals(100, $score->final_score);
        $this->assertEquals(25, $score->surplus_points);
        $this->assertEquals(25, $score->rewarded_points);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_approve(): void
    {
        $reward = $this->makePendingReward();

        $response = $this->post(route('rewards.approve', $reward));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_approve(): void
    {
        $reward     = $this->makePendingReward();
        $noPermUser = User::factory()->create();

        $response = $this->actingAs($noPermUser)
            ->post(route('rewards.approve', $reward));

        $response->assertStatus(403);
    }
}
