<?php

namespace Tests\Feature;

use App\Models\Appeal;
use App\Models\Employee;
use App\Models\Penalty;
use App\Models\PenaltyMember;
use App\Models\Setting;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppealVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;
    private User $appellantUser;    // người gửi khiếu nại
    private User $penalizedUser;    // người bị phạt (khác với người khiếu nại)
    private User $memberUser;       // thành viên liên đới trong phiếu phạt
    private User $unrelatedUser;    // không liên quan
    private Employee $appellantEmployee;
    private Employee $penalizedEmployee;
    private Employee $memberEmployee;
    private Penalty $penalty;
    private Appeal $appeal;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'default_score_per_month', 'value' => 100]);
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $reviewPerm  = Permission::firstOrCreate(['name' => 'review-appeals']);
        $viewPerm    = Permission::firstOrCreate(['name' => 'view-appeals']);
        $createAppeal = Permission::firstOrCreate(['name' => 'create-appeals']);
        $approvePerm  = Permission::firstOrCreate(['name' => 'approve-penalties']);

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->givePermissionTo([$reviewPerm, $viewPerm, $approvePerm]);

        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo([$viewPerm, $createAppeal]);

        // Reviewer/manager
        $this->reviewer = User::factory()->create();
        $this->reviewer->assignRole('manager');

        // The penalized employee (bị phạt) — they also send the appeal
        $this->appellantUser = User::factory()->create();
        $this->appellantUser->assignRole('staff');

        // Another penalized employee (bị phạt, không phải người khiếu nại)
        $this->penalizedUser = User::factory()->create();
        $this->penalizedUser->assignRole('staff');

        // Member linked in penalty
        $this->memberUser = User::factory()->create();
        $this->memberUser->assignRole('staff');

        // Unrelated staff
        $this->unrelatedUser = User::factory()->create();
        $this->unrelatedUser->assignRole('staff');

        $this->appellantEmployee = Employee::create([
            'user_id'   => $this->appellantUser->id,
            'code'      => 'EMP-C01',
            'name'      => 'Trần Văn C',
            'is_active' => true,
        ]);

        $this->penalizedEmployee = Employee::create([
            'user_id'   => $this->penalizedUser->id,
            'code'      => 'EMP-D01',
            'name'      => 'Lê Văn D',
            'is_active' => true,
        ]);

        $this->memberEmployee = Employee::create([
            'user_id'   => $this->memberUser->id,
            'code'      => 'EMP-E01',
            'name'      => 'Phạm Thị E',
            'is_active' => true,
        ]);

        Employee::create([
            'user_id'   => $this->unrelatedUser->id,
            'code'      => 'EMP-F01',
            'name'      => 'Hoàng Văn F',
            'is_active' => true,
        ]);

        $violation = Violation::create([
            'name'            => 'Vi phạm nội quy',
            'points_deducted' => 10,
            'money_deducted'  => 0,
            'is_active'       => true,
        ]);

        // Penalty against penalizedEmployee; appellant filed the appeal
        $this->penalty = Penalty::create([
            'code'                  => 'PNL-TEST-001',
            'employee_id'           => $this->penalizedEmployee->id,
            'violation_id'          => $violation->id,
            'status'                => 'approved',
            'total_points_deducted' => 10,
            'total_money_deducted'  => 0,
            'created_by'            => $this->reviewer->id,
            'approved_by'           => $this->reviewer->id,
            'approved_at'           => now(),
        ]);

        PenaltyMember::create([
            'penalty_id'      => $this->penalty->id,
            'employee_id'     => $this->memberEmployee->id,
            'points_deducted' => 5,
            'money_deducted'  => 0,
        ]);

        // Appeal filed by appellant (not the penalized employee — testing generic appellant case)
        $this->appeal = Appeal::create([
            'penalty_id'   => $this->penalty->id,
            'appellant_id' => $this->appellantUser->id,
            'reason'       => 'Khiếu nại phiếu phạt không đúng.',
            'status'       => 'pending',
        ]);
    }

    // ── Reviewer sees all ────────────────────────────────────────────────────

    public function test_reviewer_sees_all_appeals_in_index(): void
    {
        $response = $this->actingAs($this->reviewer)
            ->get(route('appeals.index'));

        $response->assertOk();
        $response->assertSee('PNL-TEST-001');
    }

    // ── Appellant sees own appeal ────────────────────────────────────────────

    public function test_appellant_sees_own_appeal_in_index(): void
    {
        $response = $this->actingAs($this->appellantUser)
            ->get(route('appeals.index'));

        $response->assertOk();
        $response->assertSee('PNL-TEST-001');
    }

    // ── Penalized employee sees appeal related to their penalty ──────────────

    public function test_penalized_employee_sees_appeal_for_their_penalty(): void
    {
        $response = $this->actingAs($this->penalizedUser)
            ->get(route('appeals.index'));

        $response->assertOk();
        $response->assertSee('PNL-TEST-001');
    }

    // ── Penalty member sees appeal related to their penalty ──────────────────

    public function test_penalty_member_sees_appeal_for_penalty_they_are_in(): void
    {
        $response = $this->actingAs($this->memberUser)
            ->get(route('appeals.index'));

        $response->assertOk();
        $response->assertSee('PNL-TEST-001');
    }

    // ── Unrelated employee sees nothing ─────────────────────────────────────

    public function test_unrelated_employee_cannot_see_others_appeal(): void
    {
        $response = $this->actingAs($this->unrelatedUser)
            ->get(route('appeals.index'));

        $response->assertOk();
        $response->assertDontSee('PNL-TEST-001');
    }

    // ── Second appeal (different penalty) not visible to unrelated user ──────

    public function test_employee_only_sees_appeals_related_to_own_penalties(): void
    {
        $violation2 = Violation::create([
            'name'            => 'Vi phạm khác',
            'points_deducted' => 5,
            'money_deducted'  => 0,
            'is_active'       => true,
        ]);

        // Penalty for unrelatedUser's employee — they appeal it
        $unrelatedEmployee = $this->unrelatedUser->employee;
        $otherPenalty = Penalty::create([
            'code'                  => 'PNL-TEST-002',
            'employee_id'           => $unrelatedEmployee->id,
            'violation_id'          => $violation2->id,
            'status'                => 'approved',
            'total_points_deducted' => 5,
            'total_money_deducted'  => 0,
            'created_by'            => $this->reviewer->id,
            'approved_by'           => $this->reviewer->id,
            'approved_at'           => now(),
        ]);

        Appeal::create([
            'penalty_id'   => $otherPenalty->id,
            'appellant_id' => $this->unrelatedUser->id,
            'reason'       => 'Tôi không sai.',
            'status'       => 'pending',
        ]);

        // unrelatedUser should only see PNL-TEST-002, not PNL-TEST-001
        $response = $this->actingAs($this->unrelatedUser)
            ->get(route('appeals.index'));

        $response->assertOk();
        $response->assertSee('PNL-TEST-002');
        $response->assertDontSee('PNL-TEST-001');
    }

    // ── Unauthenticated redirected ───────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_from_appeals(): void
    {
        $response = $this->get(route('appeals.index'));

        $response->assertRedirect(route('login'));
    }
}
