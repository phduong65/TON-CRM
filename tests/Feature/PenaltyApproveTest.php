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

class PenaltyApproveTest extends TestCase
{
    use RefreshDatabase;

    private User $approver;
    private Employee $employee;
    private Violation $violation;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'default_score_per_month', 'value' => 100]);
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'approve-penalties']);
        $role = Role::firstOrCreate(['name' => 'manager']);
        $role->givePermissionTo($perm);

        $this->approver = User::factory()->create();
        $this->approver->assignRole('manager');

        $this->employee = Employee::create([
            'code'      => 'EMP-001',
            'name'      => 'Nguyễn Văn A',
            'is_active' => true,
        ]);

        $this->violation = Violation::create([
            'name'            => 'Đi trễ',
            'points_deducted' => 20,
            'money_deducted'  => 0,
            'is_active'       => true,
        ]);
    }

    private function makePendingPenalty(int $points = 20): Penalty
    {
        return Penalty::create([
            'code'                  => 'PEN-' . uniqid(),
            'employee_id'           => $this->employee->id,
            'violation_id'          => $this->violation->id,
            'status'                => 'pending',
            'total_points_deducted' => $points,
            'total_money_deducted'  => 0,
            'created_by'            => $this->approver->id,
        ]);
    }

    // ── Approve workflow ──────────────────────────────────────────────────────

    public function test_pending_penalty_can_be_approved(): void
    {
        $penalty = $this->makePendingPenalty();

        $response = $this->actingAs($this->approver)
            ->post(route('penalties.approve', $penalty));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('penalties', [
            'id'     => $penalty->id,
            'status' => 'approved',
        ]);
    }

    public function test_approved_penalty_cannot_be_approved_again(): void
    {
        $penalty = $this->makePendingPenalty();
        $penalty->update(['status' => 'approved']);

        $response = $this->actingAs($this->approver)
            ->post(route('penalties.approve', $penalty));

        $response->assertStatus(403);
    }

    public function test_rejected_penalty_cannot_be_approved(): void
    {
        $penalty = $this->makePendingPenalty();
        $penalty->update(['status' => 'rejected']);

        $response = $this->actingAs($this->approver)
            ->post(route('penalties.approve', $penalty));

        $response->assertStatus(403);
    }

    // ── Score deduction ───────────────────────────────────────────────────────

    public function test_approve_deducts_monthly_score_from_primary_employee(): void
    {
        $penalty = $this->makePendingPenalty(20);

        $this->actingAs($this->approver)
            ->post(route('penalties.approve', $penalty));

        $score = MonthlyEmployeeScore::where('employee_id', $this->employee->id)
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();

        $this->assertNotNull($score);
        $this->assertEquals(80, $score->final_score);
        $this->assertEquals(20, $score->deducted_points);
    }

    public function test_approve_deducts_score_from_all_members(): void
    {
        $penalty = $this->makePendingPenalty(10);

        $member1 = Employee::create(['code' => 'EMP-002', 'name' => 'NV B', 'is_active' => true]);
        $member2 = Employee::create(['code' => 'EMP-003', 'name' => 'NV C', 'is_active' => true]);

        PenaltyMember::create(['penalty_id' => $penalty->id, 'employee_id' => $member1->id, 'points_deducted' => 5]);
        PenaltyMember::create(['penalty_id' => $penalty->id, 'employee_id' => $member2->id, 'points_deducted' => 8]);

        $this->actingAs($this->approver)
            ->post(route('penalties.approve', $penalty));

        // Primary employee deducted by total_points_deducted (10): 100 - 10 = 90
        $this->assertEquals(90, MonthlyEmployeeScore::where('employee_id', $this->employee->id)
            ->where('month', now()->month)->where('year', now()->year)->first()->final_score);

        // member1 deducted 5: 100 - 5 = 95
        $this->assertEquals(95, MonthlyEmployeeScore::where('employee_id', $member1->id)
            ->where('month', now()->month)->where('year', now()->year)->first()->final_score);

        // member2 deducted 8: 100 - 8 = 92
        $this->assertEquals(92, MonthlyEmployeeScore::where('employee_id', $member2->id)
            ->where('month', now()->month)->where('year', now()->year)->first()->final_score);
    }

    public function test_approve_does_not_deduct_when_points_is_zero(): void
    {
        $penalty = $this->makePendingPenalty(0);

        $this->actingAs($this->approver)
            ->post(route('penalties.approve', $penalty));

        $score = MonthlyEmployeeScore::where('employee_id', $this->employee->id)
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();

        $this->assertNull($score);
    }

    // ── Race condition prevention ─────────────────────────────────────────────

    public function test_two_sequential_approvals_only_deduct_once(): void
    {
        $penalty = $this->makePendingPenalty(20);

        // First approval — should succeed
        $this->actingAs($this->approver)
            ->post(route('penalties.approve', $penalty));

        // Second approval — should 403 (lockForUpdate + abort_if prevents double deduction)
        $response = $this->actingAs($this->approver)
            ->post(route('penalties.approve', $penalty));

        $response->assertStatus(403);

        $score = MonthlyEmployeeScore::where('employee_id', $this->employee->id)
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();

        // Score should be 80 (deducted only once), NOT 60
        $this->assertEquals(80, $score->final_score);
        $this->assertEquals(20, $score->deducted_points);
    }

    public function test_cumulative_deductions_are_correct_across_multiple_penalties(): void
    {
        $penalty1 = $this->makePendingPenalty(10);
        $penalty2 = $this->makePendingPenalty(15);

        $this->actingAs($this->approver)->post(route('penalties.approve', $penalty1));
        $this->actingAs($this->approver)->post(route('penalties.approve', $penalty2));

        $score = MonthlyEmployeeScore::where('employee_id', $this->employee->id)
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();

        $this->assertEquals(75, $score->final_score);
        $this->assertEquals(25, $score->deducted_points);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_approve(): void
    {
        $penalty = $this->makePendingPenalty();

        $response = $this->post(route('penalties.approve', $penalty));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_approve(): void
    {
        $penalty    = $this->makePendingPenalty();
        $noPermUser = User::factory()->create();

        $response = $this->actingAs($noPermUser)
            ->post(route('penalties.approve', $penalty));

        $response->assertStatus(403);
    }
}
