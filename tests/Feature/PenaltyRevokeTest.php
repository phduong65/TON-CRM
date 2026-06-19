<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MonthlyEmployeeScore;
use App\Models\Penalty;
use App\Models\PenaltyMember;
use App\Models\Setting;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PenaltyRevokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Employee $employee;
    private Violation $violation;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'default_score_per_month', 'value' => 100]);
        Setting::create(['key' => 'greenzone_min',           'value' => 90]);
        Setting::create(['key' => 'yellowzone_min',          'value' => 80]);
        Setting::create(['key' => 'orangezone_min',          'value' => 70]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $approvePerm = Permission::firstOrCreate(['name' => 'approve-penalties']);
        $revokePerm  = Permission::firstOrCreate(['name' => 'revoke-penalties']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->syncPermissions([$approvePerm, $revokePerm]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->employee = Employee::create([
            'code'      => 'EMP-REVOKE-1',
            'name'      => 'Nhân Viên Test',
            'is_active' => true,
        ]);

        $this->violation = Violation::create([
            'name'            => 'Vi phạm test',
            'points_deducted' => 20,
            'money_deducted'  => 0,
            'is_active'       => true,
        ]);
    }

    private function makeApprovedPenalty(int $points = 20): Penalty
    {
        $penalty = Penalty::create([
            'code'                  => 'PNL-TEST-' . uniqid(),
            'employee_id'           => $this->employee->id,
            'violation_id'          => $this->violation->id,
            'total_points_deducted' => $points,
            'total_money_deducted'  => 0,
            'status'                => 'pending',
            'created_by'            => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('penalties.approve', $penalty));

        return $penalty->fresh();
    }

    public function test_approved_penalty_can_be_revoked(): void
    {
        $penalty = $this->makeApprovedPenalty();

        $response = $this->actingAs($this->admin)
            ->post(route('penalties.revoke', $penalty), [
                'revoked_reason' => 'Sai thông tin vi phạm',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $penalty->refresh();
        $this->assertEquals('revoked', $penalty->status);
        $this->assertNotNull($penalty->revoked_at);
        $this->assertEquals($this->admin->id, $penalty->revoked_by);
        $this->assertEquals('Sai thông tin vi phạm', $penalty->revoked_reason);
    }

    public function test_revoke_refunds_points_to_monthly_score(): void
    {
        $penalty = $this->makeApprovedPenalty(20);

        $score = MonthlyEmployeeScore::where('employee_id', $this->employee->id)->first();
        $this->assertEquals(80, $score->final_score);

        $this->actingAs($this->admin)
            ->post(route('penalties.revoke', $penalty), [
                'revoked_reason' => 'Thu hồi test',
            ]);

        $score->refresh();
        $this->assertEquals(100, $score->final_score);
        $this->assertEquals(0, $score->deducted_points);
    }

    public function test_revoke_refund_when_score_capped_goes_to_surplus(): void
    {
        // Penalty -20 → score = 80
        $penalty = $this->makeApprovedPenalty(20);

        // Reward +30 → fills 20 to 100, surplus = 10
        $score = MonthlyEmployeeScore::where('employee_id', $this->employee->id)->first();
        $score->reward(30);
        $score->refresh();
        $this->assertEquals(100, $score->final_score);
        $this->assertEquals(10, $score->surplus_points);

        // Revoke penalty: tries to refund 20 pts, score already at 100 → all 20 to surplus
        $this->actingAs($this->admin)
            ->post(route('penalties.revoke', $penalty), [
                'revoked_reason' => 'Thu hồi sau thưởng',
            ]);

        $score->refresh();
        $this->assertEquals(100, $score->final_score);
        $this->assertEquals(30, $score->surplus_points); // 10 + 20
    }

    public function test_pending_penalty_cannot_be_revoked(): void
    {
        $penalty = Penalty::create([
            'code'                  => 'PNL-PEND-' . uniqid(),
            'employee_id'           => $this->employee->id,
            'violation_id'          => $this->violation->id,
            'total_points_deducted' => 20,
            'total_money_deducted'  => 0,
            'status'                => 'pending',
            'created_by'            => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('penalties.revoke', $penalty), [
                'revoked_reason' => 'Test',
            ]);

        $response->assertStatus(403);
    }

    public function test_revoke_requires_reason(): void
    {
        $penalty = $this->makeApprovedPenalty();

        $response = $this->actingAs($this->admin)
            ->post(route('penalties.revoke', $penalty), [
                'revoked_reason' => '',
            ]);

        $response->assertSessionHasErrors('revoked_reason');
        $penalty->refresh();
        $this->assertEquals('approved', $penalty->status);
    }

    public function test_user_without_revoke_permission_cannot_revoke(): void
    {
        $penalty = $this->makeApprovedPenalty();

        $noPermUser = User::factory()->create();
        $response   = $this->actingAs($noPermUser)
            ->post(route('penalties.revoke', $penalty), ['revoked_reason' => 'Test']);

        $response->assertStatus(403);
    }

    public function test_revoke_also_refunds_member_points(): void
    {
        $member = Employee::create([
            'code'      => 'EMP-MBR-1',
            'name'      => 'Thành Viên Test',
            'is_active' => true,
        ]);

        $penalty = Penalty::create([
            'code'                  => 'PNL-MBR-' . uniqid(),
            'employee_id'           => $this->employee->id,
            'violation_id'          => $this->violation->id,
            'total_points_deducted' => 20,
            'total_money_deducted'  => 0,
            'status'                => 'pending',
            'created_by'            => $this->admin->id,
        ]);
        PenaltyMember::create([
            'penalty_id'      => $penalty->id,
            'employee_id'     => $member->id,
            'points_deducted' => 10,
            'money_deducted'  => 0,
        ]);

        $this->actingAs($this->admin)->post(route('penalties.approve', $penalty));

        $memberScore = MonthlyEmployeeScore::where('employee_id', $member->id)->first();
        $this->assertEquals(90, $memberScore->final_score);

        $this->actingAs($this->admin)->post(route('penalties.revoke', $penalty->fresh()), [
            'revoked_reason' => 'Thu hồi liên đới',
        ]);

        $memberScore->refresh();
        $this->assertEquals(100, $memberScore->final_score);
    }
}
