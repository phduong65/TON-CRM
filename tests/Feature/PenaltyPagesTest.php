<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Penalty;
use App\Models\Setting;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Giao diện trang Phiếu phạt (index + chi tiết) sau redesign: tab trạng thái có số đếm,
 * lịch sử xử lý, nút hành động đúng quyền/trạng thái.
 */
class PenaltyPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $staffUser;
    private Employee $employee;
    private Violation $violation;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'default_score_per_month', 'value' => 100]);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['view-penalties', 'approve-penalties', 'revoke-penalties', 'create-penalties'] as $p) {
            Role::firstOrCreate(['name' => 'manager'])->givePermissionTo(Permission::firstOrCreate(['name' => $p]));
        }
        foreach (['view-penalties', 'create-appeals'] as $p) {
            Role::firstOrCreate(['name' => 'staff'])->givePermissionTo(Permission::firstOrCreate(['name' => $p]));
        }

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->staffUser = User::factory()->create();
        $this->staffUser->assignRole('staff');

        $this->employee = Employee::create([
            'code' => 'EMP-001', 'name' => 'Nguyễn Văn A', 'is_active' => true, 'user_id' => $this->staffUser->id,
        ]);
        $this->violation = Violation::create([
            'name' => 'Đi trễ', 'points_deducted' => 5, 'money_deducted' => 0, 'is_active' => true,
        ]);
    }

    private function penalty(string $status, string $code): Penalty
    {
        return Penalty::create([
            'code'                  => $code,
            'employee_id'           => $this->employee->id,
            'violation_id'          => $this->violation->id,
            'status'                => $status,
            'total_points_deducted' => 5,
            'total_money_deducted'  => 0,
            'created_by'            => $this->manager->id,
            'approved_by'           => $status === 'approved' ? $this->manager->id : null,
            'approved_at'           => $status === 'approved' ? now() : null,
        ]);
    }

    public function test_index_shows_status_tabs_with_counts(): void
    {
        $this->penalty('pending', 'PEN-P1');
        $this->penalty('pending', 'PEN-P2');
        $this->penalty('approved', 'PEN-A1');

        $response = $this->actingAs($this->manager)->get(route('penalties.index'));

        $response->assertOk();
        $html = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertMatchesRegularExpression('/Tất cả <span class="status-tab-count">3<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Chờ duyệt <span class="status-tab-count">2<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Đã duyệt <span class="status-tab-count">1<\/span>/', $html);
    }

    public function test_status_tab_filters_rows_but_keeps_counts_of_other_tabs(): void
    {
        $this->penalty('pending', 'PEN-P1');
        $this->penalty('approved', 'PEN-A1');

        $response = $this->actingAs($this->manager)->get(route('penalties.index', ['status' => 'approved']));

        $response->assertOk();
        $response->assertSee('PEN-A1');
        $response->assertDontSee('PEN-P1');
        $html = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertMatchesRegularExpression('/Chờ duyệt <span class="status-tab-count">1<\/span>/', $html);
    }

    public function test_show_pending_penalty_offers_approve_and_reject_to_approver(): void
    {
        $penalty = $this->penalty('pending', 'PEN-P1');

        $response = $this->actingAs($this->manager)->get(route('penalties.show', $penalty));

        $response->assertOk();
        $response->assertSee('Đang chờ duyệt');
        $response->assertSee('id="approvePenaltyModal"', false);
        $response->assertSee('id="rejectPenaltyModal"', false);
        $response->assertSee(route('penalties.approve', $penalty), false);
    }

    public function test_show_hides_workflow_actions_from_employee_without_permission(): void
    {
        $penalty = $this->penalty('pending', 'PEN-P1');

        $response = $this->actingAs($this->staffUser)->get(route('penalties.show', $penalty));

        $response->assertOk();
        $response->assertDontSee('id="approvePenaltyModal"', false);
        $response->assertDontSee(route('penalties.approve', $penalty), false);
    }

    public function test_show_approved_penalty_lets_owner_appeal(): void
    {
        $penalty = $this->penalty('approved', 'PEN-A1');

        $response = $this->actingAs($this->staffUser)->get(route('penalties.show', $penalty));

        $response->assertOk();
        $response->assertSee('id="appealPenaltyModal"', false);
        $response->assertDontSee('id="revokePenaltyModal"', false);
    }

    public function test_show_lists_processing_history_from_activity_log(): void
    {
        $penalty = $this->penalty('pending', 'PEN-P1');
        activity()->causedBy($this->manager)->performedOn($penalty)->log('Tạo phiếu phạt PEN-P1');
        activity()->causedBy($this->manager)->performedOn($penalty)->log('Duyệt phiếu phạt PEN-P1');

        $response = $this->actingAs($this->manager)->get(route('penalties.show', $penalty));

        $response->assertOk();
        $response->assertSee('Lịch sử xử lý');
        $response->assertSee('Duyệt phiếu phạt PEN-P1');
        $response->assertSee('timeline-dot is-approved', false);
    }
}
