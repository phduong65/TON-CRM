<?php

namespace Tests\Feature;

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

class PenaltyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $approver;
    private User $staffUserA;
    private User $staffUserB;
    private Employee $employeeA;
    private Employee $employeeB;
    private Violation $violation;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'default_score_per_month', 'value' => 100]);
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $approvePerm = Permission::firstOrCreate(['name' => 'approve-penalties']);
        $viewPerm    = Permission::firstOrCreate(['name' => 'view-penalties']);

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->givePermissionTo([$approvePerm, $viewPerm]);

        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo($viewPerm);

        $this->approver = User::factory()->create();
        $this->approver->assignRole('manager');

        $this->staffUserA = User::factory()->create();
        $this->staffUserA->assignRole('staff');

        $this->staffUserB = User::factory()->create();
        $this->staffUserB->assignRole('staff');

        $this->employeeA = Employee::create([
            'user_id'   => $this->staffUserA->id,
            'code'      => 'EMP-A01',
            'name'      => 'Nguyễn Văn A',
            'is_active' => true,
        ]);

        $this->employeeB = Employee::create([
            'user_id'   => $this->staffUserB->id,
            'code'      => 'EMP-B01',
            'name'      => 'Trần Thị B',
            'is_active' => true,
        ]);

        $this->violation = Violation::create([
            'name'            => 'Đi trễ',
            'points_deducted' => 10,
            'money_deducted'  => 0,
            'is_active'       => true,
        ]);
    }

    private function makePenalty(Employee $employee, string $status = 'pending'): Penalty
    {
        return Penalty::create([
            'code'                  => 'PNL-' . uniqid(),
            'employee_id'           => $employee->id,
            'violation_id'          => $this->violation->id,
            'status'                => $status,
            'total_points_deducted' => 10,
            'total_money_deducted'  => 0,
            'created_by'            => $this->approver->id,
        ]);
    }

    // ── Index visibility ─────────────────────────────────────────────────────

    public function test_approver_sees_all_penalties_in_index(): void
    {
        $penaltyA = $this->makePenalty($this->employeeA);
        $penaltyB = $this->makePenalty($this->employeeB);

        $response = $this->actingAs($this->approver)
            ->get(route('penalties.index'));

        $response->assertOk();
        // Each penalty card renders an onclick="openPenaltyDetail({id})" — unique per record
        $response->assertSee('openPenaltyDetail(' . $penaltyA->id . ')');
        $response->assertSee('openPenaltyDetail(' . $penaltyB->id . ')');
    }

    public function test_employee_only_sees_own_penalty_in_index(): void
    {
        $penaltyA = $this->makePenalty($this->employeeA);
        $penaltyB = $this->makePenalty($this->employeeB);

        $response = $this->actingAs($this->staffUserA)
            ->get(route('penalties.index'));

        $response->assertOk();
        $response->assertSee('openPenaltyDetail(' . $penaltyA->id . ')');
        $response->assertDontSee('openPenaltyDetail(' . $penaltyB->id . ')');
    }

    public function test_employee_sees_penalty_where_they_are_a_member(): void
    {
        // Penalty belongs to employeeB, but employeeA is a member
        $penalty = $this->makePenalty($this->employeeB);
        PenaltyMember::create([
            'penalty_id'      => $penalty->id,
            'employee_id'     => $this->employeeA->id,
            'points_deducted' => 5,
            'money_deducted'  => 0,
        ]);

        $response = $this->actingAs($this->staffUserA)
            ->get(route('penalties.index'));

        $response->assertOk();
        $response->assertSee('openPenaltyDetail(' . $penalty->id . ')');
    }

    public function test_employee_does_not_see_unrelated_penalty(): void
    {
        $penaltyB = $this->makePenalty($this->employeeB);

        $response = $this->actingAs($this->staffUserA)
            ->get(route('penalties.index'));

        $response->assertOk();
        $response->assertDontSee('openPenaltyDetail(' . $penaltyB->id . ')');
    }

    public function test_user_without_employee_record_sees_no_penalties(): void
    {
        $noEmpUser = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view-penalties']);
        $noEmpUser->givePermissionTo('view-penalties');

        $penaltyA = $this->makePenalty($this->employeeA);

        $response = $this->actingAs($noEmpUser)
            ->get(route('penalties.index'));

        $response->assertOk();
        $response->assertDontSee('openPenaltyDetail(' . $penaltyA->id . ')');
    }

    // ── Show visibility ──────────────────────────────────────────────────────

    public function test_approver_can_access_any_penalty_show(): void
    {
        $penalty = $this->makePenalty($this->employeeB);

        $response = $this->actingAs($this->approver)
            ->get(route('penalties.show', $penalty));

        $response->assertOk();
    }

    public function test_employee_can_access_own_penalty_show(): void
    {
        $penalty = $this->makePenalty($this->employeeA);

        $response = $this->actingAs($this->staffUserA)
            ->get(route('penalties.show', $penalty));

        $response->assertOk();
    }

    public function test_employee_as_member_can_access_penalty_show(): void
    {
        $penalty = $this->makePenalty($this->employeeB);
        PenaltyMember::create([
            'penalty_id'      => $penalty->id,
            'employee_id'     => $this->employeeA->id,
            'points_deducted' => 5,
            'money_deducted'  => 0,
        ]);

        $response = $this->actingAs($this->staffUserA)
            ->get(route('penalties.show', $penalty));

        $response->assertOk();
    }

    public function test_employee_cannot_access_another_employee_penalty_show(): void
    {
        $penalty = $this->makePenalty($this->employeeB);

        $response = $this->actingAs($this->staffUserA)
            ->get(route('penalties.show', $penalty));

        $response->assertStatus(403);
    }

    public function test_user_without_employee_record_cannot_access_penalty_show(): void
    {
        $noEmpUser = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view-penalties']);
        $noEmpUser->givePermissionTo('view-penalties');

        $penalty = $this->makePenalty($this->employeeA);

        $response = $this->actingAs($noEmpUser)
            ->get(route('penalties.show', $penalty));

        $response->assertStatus(403);
    }

    // ── detailJson visibility ────────────────────────────────────────────────

    public function test_approver_can_fetch_detail_json_for_any_penalty(): void
    {
        $penalty = $this->makePenalty($this->employeeB);

        $response = $this->actingAs($this->approver)
            ->getJson(route('penalties.detail-json', $penalty));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $penalty->id]);
    }

    public function test_employee_can_fetch_own_penalty_detail_json(): void
    {
        $penalty = $this->makePenalty($this->employeeA);

        $response = $this->actingAs($this->staffUserA)
            ->getJson(route('penalties.detail-json', $penalty));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $penalty->id]);
    }

    public function test_employee_cannot_fetch_other_penalty_detail_json(): void
    {
        $penalty = $this->makePenalty($this->employeeB);

        $response = $this->actingAs($this->staffUserA)
            ->getJson(route('penalties.detail-json', $penalty));

        $response->assertStatus(403);
    }
}
