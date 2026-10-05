<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Penalty;
use App\Models\Reward;
use App\Models\RewardType;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PenaltyRewardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private Employee $employeeA;
    private Employee $employeeB;
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'staff']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-penalties']));
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-rewards']));

        $this->userA = User::factory()->create(['status' => 'active']);
        $this->userA->assignRole('staff');
        $this->tokenA = $this->userA->createToken('test')->plainTextToken;

        $this->userB = User::factory()->create(['status' => 'active']);
        $this->userB->assignRole('staff');

        $this->employeeA = Employee::create(['code' => 'EMP-A', 'name' => 'A', 'user_id' => $this->userA->id, 'is_active' => true]);
        $this->employeeB = Employee::create(['code' => 'EMP-B', 'name' => 'B', 'user_id' => $this->userB->id, 'is_active' => true]);
    }

    private function authA()
    {
        return $this->withHeader('Authorization', "Bearer {$this->tokenA}");
    }

    public function test_employee_only_sees_own_penalties_via_api(): void
    {
        $violation = Violation::create(['name' => 'Đi trễ', 'points_deducted' => 10, 'money_deducted' => 0, 'is_active' => true]);

        Penalty::create(['code' => 'PNL-A', 'employee_id' => $this->employeeA->id, 'violation_id' => $violation->id, 'status' => 'pending', 'total_points_deducted' => 10, 'total_money_deducted' => 0]);
        Penalty::create(['code' => 'PNL-B', 'employee_id' => $this->employeeB->id, 'violation_id' => $violation->id, 'status' => 'pending', 'total_points_deducted' => 10, 'total_money_deducted' => 0]);

        $response = $this->authA()->getJson('/api/penalties');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('PNL-A'));
        $this->assertFalse($codes->contains('PNL-B'));
    }

    public function test_employee_cannot_view_others_penalty_detail(): void
    {
        $violation = Violation::create(['name' => 'Đi trễ', 'points_deducted' => 10, 'money_deducted' => 0, 'is_active' => true]);
        $penaltyB = Penalty::create(['code' => 'PNL-B', 'employee_id' => $this->employeeB->id, 'violation_id' => $violation->id, 'status' => 'pending', 'total_points_deducted' => 10, 'total_money_deducted' => 0]);

        $response = $this->authA()->getJson("/api/penalties/{$penaltyB->id}");

        $response->assertStatus(403);
    }

    public function test_employee_only_sees_own_rewards_via_api(): void
    {
        $rewardType = RewardType::create(['name' => 'Xuất sắc', 'default_points' => 5, 'is_active' => true]);

        Reward::create(['code' => 'RW-A', 'employee_id' => $this->employeeA->id, 'reward_type_id' => $rewardType->id, 'status' => 'pending', 'total_points_awarded' => 5]);
        Reward::create(['code' => 'RW-B', 'employee_id' => $this->employeeB->id, 'reward_type_id' => $rewardType->id, 'status' => 'pending', 'total_points_awarded' => 5]);

        $response = $this->authA()->getJson('/api/rewards');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('RW-A'));
        $this->assertFalse($codes->contains('RW-B'));
    }
}
