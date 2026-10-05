<?php

namespace Tests\Feature;

use App\Models\Appeal;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeReport;
use App\Models\Penalty;
use App\Models\StaffRequest;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Giao diện hàng đợi Khiếu nại / Báo cáo / Yêu cầu sau redesign (WF-003):
 * tab trạng thái có số đếm, hành động theo quyền, ẩn danh người báo cáo.
 */
class RequestQueuesPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $staffUser;
    private Employee $staffEmp;
    private Employee $reporterEmp;
    private Violation $violation;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $manager = Role::firstOrCreate(['name' => 'manager']);
        foreach (['view-appeals', 'review-appeals', 'view-reports', 'approve-reports', 'view-staff-requests',
                  'create-staff-requests', 'approve-staff-requests', 'view-penalties'] as $p) {
            $manager->givePermissionTo(Permission::firstOrCreate(['name' => $p]));
        }
        $staff = Role::firstOrCreate(['name' => 'staff']);
        foreach (['view-appeals', 'view-reports', 'create-reports', 'view-staff-requests', 'create-staff-requests'] as $p) {
            $staff->givePermissionTo(Permission::firstOrCreate(['name' => $p]));
        }

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
        $this->staffUser = User::factory()->create();
        $this->staffUser->assignRole('staff');

        $branch = Branch::create(['code' => 'BR', 'name' => 'Chi nhánh', 'is_active' => true]);
        $this->staffEmp = Employee::create(['code' => 'E1', 'name' => 'NV Bị phạt', 'branch_id' => $branch->id, 'is_active' => true]);
        $this->reporterEmp = Employee::create(['code' => 'E2', 'name' => 'NV Báo cáo Bí mật', 'branch_id' => $branch->id,
            'is_active' => true, 'user_id' => $this->staffUser->id]);
        $this->violation = Violation::create(['name' => 'Đi trễ', 'points_deducted' => 5, 'money_deducted' => 0, 'is_active' => true]);
    }

    private function appeal(string $status): Appeal
    {
        $penalty = Penalty::create([
            'code' => 'PEN-' . uniqid(), 'employee_id' => $this->staffEmp->id, 'violation_id' => $this->violation->id,
            'status' => 'approved', 'total_points_deducted' => 5, 'total_money_deducted' => 0, 'created_by' => $this->manager->id,
        ]);

        return Appeal::create(['penalty_id' => $penalty->id, 'appellant_id' => $this->staffUser->id, 'reason' => 'Không đúng', 'status' => $status]);
    }

    private function counts(string $html): string
    {
        return preg_replace('/\s+/', ' ', $html);
    }

    // ── Khiếu nại ────────────────────────────────────────────────────────

    public function test_appeals_index_shows_status_tabs_and_review_actions_for_reviewer(): void
    {
        $this->appeal('pending');
        $this->appeal('pending');
        $this->appeal('rejected');

        $response = $this->actingAs($this->manager)->get(route('appeals.index'));

        $response->assertOk();
        $html = $this->counts($response->getContent());
        $this->assertStringContainsString('Tất cả <span class="status-tab-count">3</span>', $html);
        $this->assertStringContainsString('Chờ xét <span class="status-tab-count">2</span>', $html);
        $response->assertSee('id="acceptAppealModal"', false);
        $response->assertSee('openAcceptAppealModal(', false);
    }

    public function test_appeals_index_hides_review_actions_without_permission(): void
    {
        $this->appeal('pending');

        $response = $this->actingAs($this->staffUser)->get(route('appeals.index'));

        $response->assertOk();
        $response->assertDontSee('id="acceptAppealModal"', false);
        $response->assertDontSee('openAcceptAppealModal(', false);
    }

    // ── Báo cáo ──────────────────────────────────────────────────────────

    private function report(string $status, ?int $createdBy = null): EmployeeReport
    {
        return EmployeeReport::create([
            'code' => 'RPT-' . uniqid(), 'reporter_employee_id' => $this->reporterEmp->id, 'reported_employee_id' => $this->staffEmp->id,
            'type' => 'individual', 'violation_id' => $this->violation->id, 'description' => 'Sự việc', 'status' => $status,
            'reward_points' => 2, 'deducted_points' => 0, 'created_by' => $createdBy ?? $this->staffUser->id,
        ]);
    }

    public function test_reports_index_counts_and_approver_sees_reporter(): void
    {
        $this->report('pending');
        $this->report('approved');

        $response = $this->actingAs($this->manager)->get(route('reports.index'));

        $response->assertOk();
        $html = $this->counts($response->getContent());
        $this->assertStringContainsString('Tất cả <span class="status-tab-count">2</span>', $html);
        $this->assertStringContainsString('Chờ duyệt <span class="status-tab-count">1</span>', $html);
        $response->assertSee('NV Báo cáo Bí mật');
    }

    public function test_report_show_offers_approve_modal_to_approver(): void
    {
        $report = $this->report('pending');

        $response = $this->actingAs($this->manager)->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertSee('id="approveReportModal"', false);
        $response->assertSee('id="rejectReportModal"', false);
    }

    public function test_report_show_keeps_reporter_anonymous_in_history_for_non_approver(): void
    {
        $report = $this->report('pending', $this->staffUser->id);
        activity()->causedBy($this->manager)->performedOn($report)->log('Từ chối báo cáo ' . $report->code . ' — NV Báo cáo Bí mật');

        $response = $this->actingAs($this->staffUser)->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertSee('Lịch sử xử lý');
        $response->assertSee('Từ chối báo cáo ' . $report->code);
        $response->assertDontSee('NV Báo cáo Bí mật');
        $response->assertDontSee('id="approveReportModal"', false);
        $response->assertSee('id="cancelReportModal"', false); // người tạo được huỷ khi còn chờ duyệt
    }

    // ── Yêu cầu ──────────────────────────────────────────────────────────

    public function test_staff_requests_status_tabs_count_within_selected_type(): void
    {
        foreach ([['overtime', 'pending'], ['overtime', 'approved'], ['business_trip', 'pending']] as $i => [$type, $status]) {
            StaffRequest::create([
                'code' => 'SR-' . $i, 'employee_id' => $this->staffEmp->id, 'type' => $type,
                'work_date' => now()->toDateString(), 'payload' => [], 'reason' => 'Test', 'status' => $status,
            ]);
        }

        $all = $this->counts($this->actingAs($this->manager)->get(route('staff-requests.index'))->assertOk()->getContent());
        $this->assertStringContainsString('Tất cả <span class="status-tab-count">3</span>', $all);
        $this->assertStringContainsString('Chờ duyệt <span class="status-tab-count">2</span>', $all);

        $overtime = $this->counts($this->actingAs($this->manager)->get(route('staff-requests.index', ['type' => 'overtime']))->assertOk()->getContent());
        $this->assertStringContainsString('Tất cả <span class="status-tab-count">2</span>', $overtime);
        $this->assertStringContainsString('Chờ duyệt <span class="status-tab-count">1</span>', $overtime);

        $this->assertStringContainsString('id="approveStaffRequestModal"', $all);
    }
}
