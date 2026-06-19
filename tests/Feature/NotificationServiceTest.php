<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeReport;
use App\Models\Notification;
use App\Models\Penalty;
use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\RewardType;
use App\Models\User;
use App\Models\Violation;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $approver;
    private User $approver2;
    private User $creator;
    private User $victimUser;
    private Employee $victimEmployee;
    private Violation $violation;
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $penaltyPerm = Permission::firstOrCreate(['name' => 'approve-penalties']);
        $rewardPerm  = Permission::firstOrCreate(['name' => 'approve-rewards']);
        $reportPerm  = Permission::firstOrCreate(['name' => 'approve-reports']);
        $role        = Role::firstOrCreate(['name' => 'manager']);
        $role->givePermissionTo([$penaltyPerm, $rewardPerm, $reportPerm]);

        $this->approver = User::factory()->create(['name' => 'Approver 1']);
        $this->approver->assignRole('manager');

        $this->approver2 = User::factory()->create(['name' => 'Approver 2']);
        $this->approver2->assignRole('manager');

        $this->creator = User::factory()->create(['name' => 'Creator']);

        $this->victimUser     = User::factory()->create(['name' => 'Victim User']);
        $this->victimEmployee = Employee::create([
            'user_id'   => $this->victimUser->id,
            'code'      => 'EMP-001',
            'name'      => 'Nguyễn Văn A',
            'is_active' => true,
        ]);

        $this->violation = Violation::create([
            'name'            => 'Đi trễ',
            'points_deducted' => 10,
            'money_deducted'  => 0,
            'is_active'       => true,
        ]);

        $this->service = app(NotificationService::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeRewardType(): RewardType
    {
        $cat = RewardCategory::create([
            'name'       => 'Khen thưởng',
            'is_active'  => true,
            'created_by' => $this->approver->id,
        ]);
        return RewardType::create([
            'reward_category_id' => $cat->id,
            'name'               => 'Xuất sắc',
            'default_points'     => 20,
            'is_active'          => true,
            'created_by'         => $this->approver->id,
        ]);
    }

    private function countFor(User $user, string $type = null): int
    {
        $q = Notification::where('user_id', $user->id);
        if ($type) {
            $q->where('type', $type);
        }
        return $q->count();
    }

    private function firstFor(User $user, string $type): ?Notification
    {
        return Notification::where('user_id', $user->id)->where('type', $type)->first();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Penalty: notifyPenaltyCreated
    // Expected recipients: all approvers + creator
    // ─────────────────────────────────────────────────────────────────────────

    public function test_penalty_created_notifies_all_approvers(): void
    {
        $penalty = Penalty::create([
            'code' => 'PEN-001', 'employee_id' => $this->victimEmployee->id,
            'violation_id' => $this->violation->id, 'status' => 'pending',
            'total_points_deducted' => 10, 'total_money_deducted' => 0,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->creator);
        $this->service->notifyPenaltyCreated($penalty);

        $this->assertEquals(1, $this->countFor($this->approver, 'penalty_created'));
        $this->assertEquals(1, $this->countFor($this->approver2, 'penalty_created'));
    }

    public function test_penalty_created_sends_confirmation_to_non_approver_creator(): void
    {
        $penalty = Penalty::create([
            'code' => 'PEN-001', 'employee_id' => $this->victimEmployee->id,
            'violation_id' => $this->violation->id, 'status' => 'pending',
            'total_points_deducted' => 10, 'total_money_deducted' => 0,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->creator);
        $this->service->notifyPenaltyCreated($penalty);

        $this->assertEquals(1, $this->countFor($this->creator, 'penalty_created'));
        $notification = $this->firstFor($this->creator, 'penalty_created');
        $this->assertStringContainsString('gửi duyệt', $notification->title);
    }

    public function test_penalty_created_no_duplicate_when_creator_is_approver(): void
    {
        // Creator also has approve-penalties permission
        $penalty = Penalty::create([
            'code' => 'PEN-001', 'employee_id' => $this->victimEmployee->id,
            'violation_id' => $this->violation->id, 'status' => 'pending',
            'total_points_deducted' => 10, 'total_money_deducted' => 0,
            'created_by' => $this->approver->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyPenaltyCreated($penalty);

        $this->assertEquals(1, $this->countFor($this->approver, 'penalty_created'));
    }

    public function test_penalty_created_does_not_notify_victim_or_outsiders(): void
    {
        $outsider = User::factory()->create();

        $penalty = Penalty::create([
            'code' => 'PEN-001', 'employee_id' => $this->victimEmployee->id,
            'violation_id' => $this->violation->id, 'status' => 'pending',
            'total_points_deducted' => 10, 'total_money_deducted' => 0,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->creator);
        $this->service->notifyPenaltyCreated($penalty);

        $this->assertEquals(0, $this->countFor($this->victimUser));
        $this->assertEquals(0, $this->countFor($outsider));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Penalty: notifyPenaltyApproved
    // Expected recipients: creator + all approvers (excl. actor) + affected employees
    // ─────────────────────────────────────────────────────────────────────────

    public function test_penalty_approved_notifies_creator(): void
    {
        $penalty = Penalty::create([
            'code' => 'PEN-001', 'employee_id' => $this->victimEmployee->id,
            'violation_id' => $this->violation->id, 'status' => 'approved',
            'total_points_deducted' => 10, 'total_money_deducted' => 0,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyPenaltyApproved($penalty);

        $this->assertEquals(1, $this->countFor($this->creator, 'penalty_approved'));
    }

    public function test_penalty_approved_notifies_other_approvers_not_actor(): void
    {
        $penalty = Penalty::create([
            'code' => 'PEN-001', 'employee_id' => $this->victimEmployee->id,
            'violation_id' => $this->violation->id, 'status' => 'approved',
            'total_points_deducted' => 10, 'total_money_deducted' => 0,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyPenaltyApproved($penalty);

        $this->assertEquals(1, $this->countFor($this->approver2, 'penalty_approved'));
        $this->assertEquals(0, $this->countFor($this->approver, 'penalty_approved'));
    }

    public function test_penalty_approved_notifies_affected_employee_linked_user(): void
    {
        $penalty = Penalty::create([
            'code' => 'PEN-001', 'employee_id' => $this->victimEmployee->id,
            'violation_id' => $this->violation->id, 'status' => 'approved',
            'total_points_deducted' => 10, 'total_money_deducted' => 0,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyPenaltyApproved($penalty);

        $this->assertEquals(1, $this->countFor($this->victimUser, 'penalty_approved'));
    }

    public function test_penalty_approved_does_not_notify_outsiders(): void
    {
        $outsider = User::factory()->create();

        $penalty = Penalty::create([
            'code' => 'PEN-001', 'employee_id' => $this->victimEmployee->id,
            'violation_id' => $this->violation->id, 'status' => 'approved',
            'total_points_deducted' => 10, 'total_money_deducted' => 0,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyPenaltyApproved($penalty);

        $this->assertEquals(0, $this->countFor($outsider));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Penalty: notifyPenaltyRejected
    // Expected recipients: creator + all approvers (excl. actor)
    // NOT: affected employees
    // ─────────────────────────────────────────────────────────────────────────

    public function test_penalty_rejected_notifies_creator_and_other_approvers(): void
    {
        $penalty = Penalty::create([
            'code' => 'PEN-001', 'employee_id' => $this->victimEmployee->id,
            'violation_id' => $this->violation->id, 'status' => 'rejected',
            'total_points_deducted' => 10, 'total_money_deducted' => 0,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyPenaltyRejected($penalty, 'Không đủ bằng chứng');

        $this->assertEquals(1, $this->countFor($this->creator, 'penalty_rejected'));
        $this->assertEquals(1, $this->countFor($this->approver2, 'penalty_rejected'));
    }

    public function test_penalty_rejected_does_not_self_notify_actor(): void
    {
        $penalty = Penalty::create([
            'code' => 'PEN-001', 'employee_id' => $this->victimEmployee->id,
            'violation_id' => $this->violation->id, 'status' => 'rejected',
            'total_points_deducted' => 10, 'total_money_deducted' => 0,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyPenaltyRejected($penalty, 'Lý do từ chối');

        $this->assertEquals(0, $this->countFor($this->approver, 'penalty_rejected'));
    }

    public function test_penalty_rejected_does_not_notify_affected_employee(): void
    {
        $penalty = Penalty::create([
            'code' => 'PEN-001', 'employee_id' => $this->victimEmployee->id,
            'violation_id' => $this->violation->id, 'status' => 'rejected',
            'total_points_deducted' => 10, 'total_money_deducted' => 0,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyPenaltyRejected($penalty, 'Lý do từ chối');

        $this->assertEquals(0, $this->countFor($this->victimUser));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reward: notifyRewardCreated
    // Expected recipients: all approvers + creator
    // ─────────────────────────────────────────────────────────────────────────

    public function test_reward_created_notifies_approvers_and_creator(): void
    {
        $reward = Reward::create([
            'code' => 'REW-001', 'reward_type_id' => $this->makeRewardType()->id,
            'employee_id' => $this->victimEmployee->id, 'status' => 'pending',
            'total_points_awarded' => 20, 'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->creator);
        $this->service->notifyRewardCreated($reward);

        $this->assertEquals(1, $this->countFor($this->approver, 'reward_created'));
        $this->assertEquals(1, $this->countFor($this->approver2, 'reward_created'));
        $this->assertEquals(1, $this->countFor($this->creator, 'reward_created'));
        // Rewarded employee should NOT be notified at creation
        $this->assertEquals(0, $this->countFor($this->victimUser));
    }

    public function test_reward_created_no_duplicate_when_creator_is_approver(): void
    {
        $reward = Reward::create([
            'code' => 'REW-001', 'reward_type_id' => $this->makeRewardType()->id,
            'employee_id' => $this->victimEmployee->id, 'status' => 'pending',
            'total_points_awarded' => 20, 'created_by' => $this->approver->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyRewardCreated($reward);

        $this->assertEquals(1, $this->countFor($this->approver, 'reward_created'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reward: notifyRewardApproved
    // Expected recipients: creator + approvers (excl. actor) + rewarded employee
    // ─────────────────────────────────────────────────────────────────────────

    public function test_reward_approved_notifies_creator_approvers_and_rewarded_employee(): void
    {
        $reward = Reward::create([
            'code' => 'REW-001', 'reward_type_id' => $this->makeRewardType()->id,
            'employee_id' => $this->victimEmployee->id, 'status' => 'approved',
            'total_points_awarded' => 20, 'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyRewardApproved($reward);

        $this->assertEquals(1, $this->countFor($this->creator, 'reward_approved'));
        $this->assertEquals(1, $this->countFor($this->approver2, 'reward_approved'));
        $this->assertEquals(1, $this->countFor($this->victimUser, 'reward_approved'));
        $this->assertEquals(0, $this->countFor($this->approver, 'reward_approved'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reward: notifyRewardRejected
    // Expected recipients: creator + approvers (excl. actor)
    // NOT: rewarded employee
    // ─────────────────────────────────────────────────────────────────────────

    public function test_reward_rejected_notifies_creator_and_approvers_not_employee(): void
    {
        $reward = Reward::create([
            'code' => 'REW-001', 'reward_type_id' => $this->makeRewardType()->id,
            'employee_id' => $this->victimEmployee->id, 'status' => 'rejected',
            'total_points_awarded' => 20, 'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyRewardRejected($reward, 'Không đủ tiêu chí');

        $this->assertEquals(1, $this->countFor($this->creator, 'reward_rejected'));
        $this->assertEquals(1, $this->countFor($this->approver2, 'reward_rejected'));
        $this->assertEquals(0, $this->countFor($this->approver, 'reward_rejected'));
        $this->assertEquals(0, $this->countFor($this->victimUser));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Report: notifyReportCreated
    // Expected recipients: all approvers + reporter
    // NOT: reported person (they don't know until approval)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_report_created_notifies_approvers_and_reporter_not_reported(): void
    {
        $reporterUser = User::factory()->create(['name' => 'Reporter User']);
        $reportedUser = User::factory()->create(['name' => 'Reported User']);

        $reporter = Employee::create(['user_id' => $reporterUser->id, 'code' => 'EMP-REP', 'name' => 'Reporter', 'is_active' => true]);
        $reported = Employee::create(['user_id' => $reportedUser->id, 'code' => 'EMP-RPD', 'name' => 'Reported', 'is_active' => true]);

        $report = EmployeeReport::create([
            'code' => 'RPT-001', 'reporter_employee_id' => $reporter->id,
            'reported_employee_id' => $reported->id, 'violation_id' => $this->violation->id,
            'description' => 'Test report', 'status' => 'pending',
            'reward_points' => 5, 'created_by' => $reporterUser->id,
        ]);

        $this->actingAs($reporterUser);
        $this->service->notifyReportCreated($report);

        $this->assertEquals(1, $this->countFor($this->approver, 'report_created'));
        $this->assertEquals(1, $this->countFor($this->approver2, 'report_created'));
        $this->assertEquals(1, $this->countFor($reporterUser, 'report_created'));
        // Reported person must NOT be notified at creation
        $this->assertEquals(0, $this->countFor($reportedUser));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Report: notifyReportApproved
    // Expected recipients: approvers (excl. actor) + reporter + reported person
    // ─────────────────────────────────────────────────────────────────────────

    public function test_report_approved_notifies_reporter_reported_and_other_approvers(): void
    {
        $reporterUser = User::factory()->create(['name' => 'Reporter User']);
        $reportedUser = User::factory()->create(['name' => 'Reported User']);

        $reporter = Employee::create(['user_id' => $reporterUser->id, 'code' => 'EMP-REP', 'name' => 'Reporter', 'is_active' => true]);
        $reported = Employee::create(['user_id' => $reportedUser->id, 'code' => 'EMP-RPD', 'name' => 'Reported', 'is_active' => true]);

        $report = EmployeeReport::create([
            'code' => 'RPT-001', 'reporter_employee_id' => $reporter->id,
            'reported_employee_id' => $reported->id, 'violation_id' => $this->violation->id,
            'description' => 'Test report', 'status' => 'approved', 'reward_points' => 5, 'created_by' => $reporterUser->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyReportApproved($report);

        $this->assertEquals(1, $this->countFor($reporterUser, 'report_approved'));
        $this->assertEquals(1, $this->countFor($reportedUser, 'report_approved'));
        $this->assertEquals(1, $this->countFor($this->approver2, 'report_approved'));
        $this->assertEquals(0, $this->countFor($this->approver, 'report_approved'));
    }

    public function test_report_approved_does_not_notify_outsiders(): void
    {
        $outsider     = User::factory()->create();
        $reporterUser = User::factory()->create(['name' => 'Reporter User']);
        $reportedUser = User::factory()->create(['name' => 'Reported User']);

        $reporter = Employee::create(['user_id' => $reporterUser->id, 'code' => 'EMP-REP', 'name' => 'Reporter', 'is_active' => true]);
        $reported = Employee::create(['user_id' => $reportedUser->id, 'code' => 'EMP-RPD', 'name' => 'Reported', 'is_active' => true]);

        $report = EmployeeReport::create([
            'code' => 'RPT-001', 'reporter_employee_id' => $reporter->id,
            'reported_employee_id' => $reported->id, 'violation_id' => $this->violation->id,
            'description' => 'Test report', 'status' => 'approved', 'reward_points' => 5, 'created_by' => $reporterUser->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyReportApproved($report);

        $this->assertEquals(0, $this->countFor($outsider));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Report: notifyReportRejected
    // Expected recipients: reporter + approvers (excl. actor)
    // NOT: reported person (they were never penalized, no need to know)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_report_rejected_notifies_reporter_and_approvers_not_reported(): void
    {
        $reporterUser = User::factory()->create(['name' => 'Reporter User']);
        $reportedUser = User::factory()->create(['name' => 'Reported User']);

        $reporter = Employee::create(['user_id' => $reporterUser->id, 'code' => 'EMP-REP', 'name' => 'Reporter', 'is_active' => true]);
        $reported = Employee::create(['user_id' => $reportedUser->id, 'code' => 'EMP-RPD', 'name' => 'Reported', 'is_active' => true]);

        $report = EmployeeReport::create([
            'code' => 'RPT-001', 'reporter_employee_id' => $reporter->id,
            'reported_employee_id' => $reported->id, 'violation_id' => $this->violation->id,
            'description' => 'Test report', 'status' => 'rejected', 'reward_points' => 5, 'created_by' => $reporterUser->id,
        ]);

        $this->actingAs($this->approver);
        $this->service->notifyReportRejected($report, 'Thiếu bằng chứng');

        $this->assertEquals(1, $this->countFor($reporterUser, 'report_rejected'));
        $this->assertEquals(1, $this->countFor($this->approver2, 'report_rejected'));
        $this->assertEquals(0, $this->countFor($this->approver, 'report_rejected'));
        // Reported person must NOT be notified on rejection
        $this->assertEquals(0, $this->countFor($reportedUser));
    }
}
