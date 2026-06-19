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

class RewardRevokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Employee $employee;
    private RewardType $rewardType;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'default_score_per_month', 'value' => 100]);
        Setting::create(['key' => 'greenzone_min',           'value' => 90]);
        Setting::create(['key' => 'yellowzone_min',          'value' => 80]);
        Setting::create(['key' => 'orangezone_min',          'value' => 70]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $approvePerm = Permission::firstOrCreate(['name' => 'approve-rewards']);
        $revokePerm  = Permission::firstOrCreate(['name' => 'revoke-rewards']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->syncPermissions([$approvePerm, $revokePerm]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->employee = Employee::create([
            'code'      => 'EMP-RWD-1',
            'name'      => 'Nhân Viên Thưởng',
            'is_active' => true,
        ]);

        $category = RewardCategory::create([
            'name'       => 'Khen thưởng',
            'is_active'  => true,
            'created_by' => $this->admin->id,
        ]);

        $this->rewardType = RewardType::create([
            'reward_category_id' => $category->id,
            'name'               => 'Nhân viên xuất sắc',
            'default_points'     => 10,
            'is_active'          => true,
            'created_by'         => $this->admin->id,
        ]);
    }

    private function makeApprovedReward(int $points = 20): Reward
    {
        $reward = Reward::create([
            'code'                 => 'RWD-TEST-' . uniqid(),
            'target_type'          => 'individual',
            'employee_id'          => $this->employee->id,
            'reward_type_id'       => $this->rewardType->id,
            'total_points_awarded' => $points,
            'status'               => 'pending',
            'created_by'           => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('rewards.approve', $reward));

        return $reward->fresh();
    }

    public function test_approved_reward_can_be_revoked(): void
    {
        $reward = $this->makeApprovedReward();

        $response = $this->actingAs($this->admin)
            ->post(route('rewards.revoke', $reward), [
                'revoked_reason' => 'Nhân viên nghỉ việc',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $reward->refresh();
        $this->assertEquals('revoked', $reward->status);
        $this->assertNotNull($reward->revoked_at);
        $this->assertEquals($this->admin->id, $reward->revoked_by);
        $this->assertEquals('Nhân viên nghỉ việc', $reward->revoked_reason);
    }

    public function test_revoke_removes_surplus_points_when_score_was_full(): void
    {
        // Employee at 100 pts, reward 20 → surplus = 20
        $reward = $this->makeApprovedReward(20);

        $score = MonthlyEmployeeScore::where('employee_id', $this->employee->id)->first();
        $this->assertEquals(100, $score->final_score);
        $this->assertEquals(20, $score->surplus_points);

        $this->actingAs($this->admin)
            ->post(route('rewards.revoke', $reward), [
                'revoked_reason' => 'Thu hồi khi đủ điểm',
            ]);

        $score->refresh();
        $this->assertEquals(100, $score->final_score);
        $this->assertEquals(0, $score->surplus_points);
        $this->assertEquals(0, $score->rewarded_points);
    }

    public function test_revoke_fills_from_surplus_first_then_deducts_base(): void
    {
        // Deduct 30 first → score = 70
        $score = MonthlyEmployeeScore::ensureExists($this->employee->id, now()->month, now()->year);
        $score->deduct(30);
        $score->refresh();
        $this->assertEquals(70, $score->final_score);

        // Award 50 → fills 30 to reach 100, surplus = 20
        $reward = $this->makeApprovedReward(50);
        $score->refresh();
        $this->assertEquals(100, $score->final_score);
        $this->assertEquals(20, $score->surplus_points);

        // Revoke reward of 50 → remove 20 from surplus, then 30 from base
        $this->actingAs($this->admin)
            ->post(route('rewards.revoke', $reward), [
                'revoked_reason' => 'Thu hồi có hiệu số',
            ]);

        $score->refresh();
        $this->assertEquals(70, $score->final_score);
        $this->assertEquals(0, $score->surplus_points);
        $this->assertEquals(0, $score->rewarded_points);
    }

    public function test_pending_reward_cannot_be_revoked(): void
    {
        $reward = Reward::create([
            'code'                 => 'RWD-PEND-' . uniqid(),
            'target_type'          => 'individual',
            'employee_id'          => $this->employee->id,
            'reward_type_id'       => $this->rewardType->id,
            'total_points_awarded' => 10,
            'status'               => 'pending',
            'created_by'           => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('rewards.revoke', $reward), ['revoked_reason' => 'Test']);

        $response->assertStatus(403);
    }

    public function test_revoke_requires_reason(): void
    {
        $reward = $this->makeApprovedReward();

        $response = $this->actingAs($this->admin)
            ->post(route('rewards.revoke', $reward), ['revoked_reason' => '']);

        $response->assertSessionHasErrors('revoked_reason');
        $reward->refresh();
        $this->assertEquals('approved', $reward->status);
    }

    public function test_user_without_revoke_permission_cannot_revoke(): void
    {
        $reward = $this->makeApprovedReward();

        $noPermUser = User::factory()->create();
        $response   = $this->actingAs($noPermUser)
            ->post(route('rewards.revoke', $reward), ['revoked_reason' => 'Test']);

        $response->assertStatus(403);
    }

    public function test_revoke_reverses_member_points(): void
    {
        $member = Employee::create([
            'code'      => 'EMP-MBR-RWD',
            'name'      => 'Thành Viên Thưởng',
            'is_active' => true,
        ]);

        $reward = Reward::create([
            'code'                 => 'RWD-MBR-' . uniqid(),
            'target_type'          => 'individual',
            'employee_id'          => $this->employee->id,
            'reward_type_id'       => $this->rewardType->id,
            'total_points_awarded' => 10,
            'status'               => 'pending',
            'created_by'           => $this->admin->id,
        ]);
        RewardMember::create([
            'reward_id'      => $reward->id,
            'employee_id'    => $member->id,
            'points_awarded' => 15,
        ]);

        $this->actingAs($this->admin)->post(route('rewards.approve', $reward));

        $memberScore = MonthlyEmployeeScore::where('employee_id', $member->id)->first();
        $this->assertEquals(15, $memberScore->surplus_points);

        $this->actingAs($this->admin)->post(route('rewards.revoke', $reward->fresh()), [
            'revoked_reason' => 'Thu hồi thành viên',
        ]);

        $memberScore->refresh();
        $this->assertEquals(0, $memberScore->surplus_points);
        $this->assertEquals(0, $memberScore->rewarded_points);
    }
}
