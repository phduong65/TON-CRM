<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeReport;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\Penalty;
use App\Models\Reward;
use App\Models\ShiftSwapRequest;
use App\Models\StaffRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationService
{
    public function sendToUser(
        int $userId,
        string $type,
        string $title,
        string $body = '',
        array $data = [],
        ?int $createdBy = null
    ): Notification {
        return Notification::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body ?: null,
            'data'       => $data ?: null,
            'created_by' => $createdBy,
        ]);
    }

    public function sendToAll(
        string $type,
        string $title,
        string $body = '',
        array $data = [],
        ?int $createdBy = null
    ): int {
        $userIds = User::pluck('id');

        foreach ($userIds as $userId) {
            Notification::create([
                'user_id'    => $userId,
                'type'       => $type,
                'title'      => $title,
                'body'       => $body ?: null,
                'data'       => $data ?: null,
                'created_by' => $createdBy,
            ]);
        }

        return $userIds->count();
    }

    public function sendToUsersWithPermission(
        string $permission,
        string $type,
        string $title,
        string $body = '',
        array $data = []
    ): void {
        $userIds = User::permission($permission)->pluck('id');

        foreach ($userIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type'    => $type,
                'title'   => $title,
                'body'    => $body ?: null,
                'data'    => $data ?: null,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function approverIds(string $permission): Collection
    {
        return User::permission($permission)->pluck('id');
    }

    private function employeeUserIds(Collection $employeeIds): Collection
    {
        return Employee::whereIn('id', $employeeIds->filter()->unique())
            ->whereNotNull('user_id')
            ->pluck('user_id');
    }

    /**
     * Send to a deduplicated set of user IDs, skipping the acting user ($excludeId).
     */
    private function dispatchToMany(
        Collection $userIds,
        string $type,
        string $title,
        string $body,
        array $data,
        ?int $excludeId = null
    ): void {
        foreach ($userIds->unique()->filter() as $userId) {
            if ($excludeId !== null && (int) $userId === (int) $excludeId) {
                continue;
            }
            Notification::create([
                'user_id' => $userId,
                'type'    => $type,
                'title'   => $title,
                'body'    => $body ?: null,
                'data'    => $data ?: null,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Penalty notifications
    // Recipients: người có quyền duyệt + người tạo + người bị phạt
    // -------------------------------------------------------------------------

    public function notifyPenaltyCreated(Penalty $penalty): void
    {
        $penalty->loadMissing(['violation', 'employee', 'members']);

        $data        = ['penalty_id' => $penalty->id];
        $approverIds = $this->approverIds('approve-penalties');

        // Notify approvers
        $approverBody = sprintf(
            '%s vừa tạo phiếu phạt %s — NV: %s — Vi phạm: %s',
            auth()->user()->name,
            $penalty->code ?? '#' . $penalty->id,
            $penalty->employee?->name ?? '—',
            $penalty->violation?->name ?? '—'
        );
        $this->dispatchToMany($approverIds, 'penalty_created', 'Phiếu phạt mới cần duyệt', $approverBody, $data);

        // Notify creator (confirmation) — only if they are not already an approver
        if ($penalty->created_by && ! $approverIds->contains($penalty->created_by)) {
            $creatorBody = sprintf(
                'Phiếu phạt %s của bạn đã được gửi đi và đang chờ phê duyệt',
                $penalty->code ?? '#' . $penalty->id
            );
            $this->sendToUser($penalty->created_by, 'penalty_created', 'Phiếu phạt đã gửi duyệt', $creatorBody, $data);
        }

        // Nhân viên bị phạt chỉ nhận thông báo khi phiếu được duyệt (notifyPenaltyApproved),
        // không thông báo ở giai đoạn pending để tránh gây lo lắng trước khi có kết quả chính thức.
    }

    public function notifyPenaltyApproved(Penalty $penalty): void
    {
        $penalty->loadMissing(['violation', 'employee', 'members.employee']);

        $title = 'Phiếu phạt đã được duyệt';
        $body  = sprintf(
            'Phiếu phạt %s đã được duyệt — NV: %s — Trừ %s điểm%s',
            $penalty->code ?? '#' . $penalty->id,
            $penalty->employee?->name ?? '—',
            $penalty->total_points_deducted,
            $penalty->total_money_deducted > 0
                ? ' & ' . number_format($penalty->total_money_deducted, 0, ',', '.') . '₫'
                : ''
        );
        $data = ['penalty_id' => $penalty->id];

        $employeeIds = $penalty->members->pluck('employee_id')
            ->push($penalty->employee_id)
            ->unique()
            ->filter();

        $recipients = $this->approverIds('approve-penalties')
            ->push($penalty->created_by)
            ->merge($this->employeeUserIds($employeeIds));

        // Exclude the current approver from self-notification
        $this->dispatchToMany($recipients, 'penalty_approved', $title, $body, $data, auth()->id());
    }

    public function notifyPenaltyRejected(Penalty $penalty, string $reason): void
    {
        $penalty->loadMissing(['violation', 'employee']);

        $body = sprintf(
            'Phiếu phạt %s đã bị từ chối — NV: %s — Lý do: %s',
            $penalty->code ?? '#' . $penalty->id,
            $penalty->employee?->name ?? '—',
            Str::limit($reason, 80)
        );
        $data = ['penalty_id' => $penalty->id];

        $recipients = $this->approverIds('approve-penalties')
            ->push($penalty->created_by);

        // Exclude the current rejecter from self-notification
        $this->dispatchToMany($recipients, 'penalty_rejected', 'Phiếu phạt bị từ chối', $body, $data, auth()->id());
    }

    // -------------------------------------------------------------------------
    // Reward notifications
    // Recipients: người có quyền duyệt + người tạo + người được thưởng
    // -------------------------------------------------------------------------

    public function notifyRewardCreated(Reward $reward): void
    {
        $reward->loadMissing(['rewardType', 'employee', 'members']);

        $data        = ['reward_id' => $reward->id];
        $approverIds = $this->approverIds('approve-rewards');

        // Notify approvers
        $approverBody = sprintf(
            '%s vừa tạo phiếu thưởng %s — NV: %s — Loại: %s — +%s điểm',
            auth()->user()->name,
            $reward->code,
            $reward->employee?->name ?? '—',
            $reward->rewardType?->name ?? '—',
            number_format($reward->total_points_awarded)
        );
        $this->dispatchToMany($approverIds, 'reward_created', 'Phiếu thưởng mới cần duyệt', $approverBody, $data);

        // Notify creator (confirmation) — only if they are not already an approver
        if ($reward->created_by && ! $approverIds->contains($reward->created_by)) {
            $creatorBody = sprintf(
                'Phiếu thưởng %s của bạn đã được gửi đi và đang chờ phê duyệt',
                $reward->code
            );
            $this->sendToUser($reward->created_by, 'reward_created', 'Phiếu thưởng đã gửi duyệt', $creatorBody, $data);
        }

        // Nhân viên được thưởng chỉ nhận thông báo khi phiếu được duyệt (notifyRewardApproved),
        // không thông báo ở giai đoạn pending để tránh tạo kỳ vọng trước khi có kết quả chính thức.
    }

    public function notifyRewardApproved(Reward $reward): void
    {
        $reward->loadMissing(['rewardType', 'employee', 'members.employee']);

        $title = 'Phiếu thưởng đã được duyệt';
        $body  = sprintf(
            'Phiếu thưởng %s đã được duyệt — NV: %s — Cộng +%s điểm',
            $reward->code,
            $reward->employee?->name ?? '—',
            number_format($reward->total_points_awarded)
        );
        $data = ['reward_id' => $reward->id];

        $employeeIds = $reward->members->pluck('employee_id')
            ->push($reward->employee_id)
            ->unique()
            ->filter();

        $recipients = $this->approverIds('approve-rewards')
            ->push($reward->created_by)
            ->merge($this->employeeUserIds($employeeIds));

        // Exclude the current approver from self-notification
        $this->dispatchToMany($recipients, 'reward_approved', $title, $body, $data, auth()->id());
    }

    public function notifyRewardRejected(Reward $reward, string $reason): void
    {
        $reward->loadMissing(['rewardType', 'employee']);

        $body = sprintf(
            'Phiếu thưởng %s đã bị từ chối — NV: %s — Lý do: %s',
            $reward->code,
            $reward->employee?->name ?? '—',
            Str::limit($reason, 80)
        );
        $data = ['reward_id' => $reward->id];

        $recipients = $this->approverIds('approve-rewards')
            ->push($reward->created_by);

        // Exclude the current rejecter from self-notification
        $this->dispatchToMany($recipients, 'reward_rejected', 'Phiếu thưởng bị từ chối', $body, $data, auth()->id());
    }

    // -------------------------------------------------------------------------
    // Report notifications
    // Recipients: người có quyền duyệt + người báo cáo + người bị báo cáo (khi duyệt)
    // -------------------------------------------------------------------------

    public function notifyReportCreated(EmployeeReport $report): void
    {
        $report->loadMissing(['reporter', 'reported', 'team', 'members.employee', 'violation']);

        $data        = ['report_id' => $report->id];
        $approverIds = $this->approverIds('approve-reports');
        $reporterUserId = $report->reporter?->user_id;
        $targetLabel = $this->reportTargetLabel($report);

        // Notify approvers
        $approverBody = sprintf(
            '%s vừa tạo báo cáo %s — Báo cáo: %s — Vi phạm: %s',
            auth()->user()->name,
            $report->code,
            $targetLabel,
            $report->violation?->name ?? 'Không xác định'
        );
        $this->dispatchToMany($approverIds, 'report_created', 'Báo cáo mới cần duyệt', $approverBody, $data);

        // Notify reporter (confirmation) — only if they are not already an approver
        if ($reporterUserId && ! $approverIds->contains($reporterUserId)) {
            $creatorBody = sprintf(
                'Báo cáo %s của bạn đã được gửi đi và đang chờ phê duyệt',
                $report->code
            );
            $this->sendToUser($reporterUserId, 'report_created', 'Báo cáo đã gửi duyệt', $creatorBody, $data);
        }

        // The reported person is NOT notified at creation — only upon approval
    }

    public function notifyReportApproved(EmployeeReport $report): void
    {
        $report->loadMissing(['reporter', 'reported', 'team', 'members.employee', 'violation']);

        $data           = ['report_id' => $report->id];
        $deducted       = $report->violation?->points_deducted ?? 0;
        $reporterUserId = $report->reporter?->user_id;
        $approverIds    = $this->approverIds('approve-reports');
        $actorId        = auth()->id();
        $targetLabel    = $this->reportTargetLabel($report);

        // Notify other approvers
        $approverBody = sprintf(
            'Báo cáo %s đã được duyệt — Người báo cáo: %s — Người bị báo cáo: %s',
            $report->code,
            $report->reporter?->name ?? '—',
            $targetLabel
        );
        $this->dispatchToMany($approverIds, 'report_approved', 'Báo cáo đã được duyệt', $approverBody, $data, $actorId);

        // Notify reporter: their report was accepted & points awarded
        if ($reporterUserId && (int) $reporterUserId !== (int) $actorId) {
            $body = sprintf(
                'Báo cáo %s của bạn đã được duyệt — Cộng +%s điểm vào tài khoản',
                $report->code,
                $report->reward_points
            );
            $this->sendToUser($reporterUserId, 'report_approved', 'Báo cáo được duyệt', $body, $data);
        }

        // Notify each targeted employee that was actually deducted points
        // (Admin/Director are exempt from scoring and are not notified as "penalized")
        foreach ($report->chargeableTargetEmployees() as $target) {
            $reportedUserId = $target->user_id;

            if (!$reportedUserId
                || (int) $reportedUserId === (int) $actorId
                || (int) $reportedUserId === (int) $reporterUserId
            ) {
                continue;
            }

            $body = sprintf(
                'Bạn bị báo cáo vi phạm %s — %s — %s',
                $report->violation?->name ?? 'vi phạm nội quy',
                $deducted > 0 ? "Trừ {$deducted} điểm" : 'Không trừ điểm',
                $report->code
            );
            $this->sendToUser($reportedUserId, 'report_approved', 'Thông báo vi phạm từ báo cáo', $body, $data);
        }
    }

    private function reportTargetLabel(EmployeeReport $report): string
    {
        return match ($report->type) {
            'team'  => 'Team ' . ($report->team?->name ?? '—'),
            'joint' => $report->targetEmployees()->pluck('name')->implode(', ') ?: '—',
            default => $report->reported?->name ?? '—',
        };
    }

    public function notifyReportRejected(EmployeeReport $report, string $reason): void
    {
        $report->loadMissing(['reporter', 'reported']);

        $reporterUserId = $report->reporter?->user_id;
        $data           = ['report_id' => $report->id];

        $body = sprintf(
            'Báo cáo %s của bạn bị từ chối — Lý do: %s',
            $report->code,
            Str::limit($reason, 80)
        );

        $recipients = $this->approverIds('approve-reports')
            ->push($reporterUserId);

        // Exclude the current rejecter from self-notification
        // The reported person is NOT notified on rejection (they were not penalized)
        $this->dispatchToMany($recipients, 'report_rejected', 'Báo cáo bị từ chối', $body, $data, auth()->id());
    }

    // -------------------------------------------------------------------------
    // Leave request notifications
    // Recipients: người có quyền duyệt + chính nhân viên xin nghỉ
    // -------------------------------------------------------------------------

    public function notifyLeaveRequestCreated(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->loadMissing('employee');

        $data        = ['leave_request_id' => $leaveRequest->id];
        $approverIds = $this->approverIds('approve-leave-requests');

        $approverBody = sprintf(
            '%s vừa gửi đơn xin nghỉ %s — Từ %s đến %s',
            $leaveRequest->employee?->name ?? '—',
            $leaveRequest->code,
            $leaveRequest->date_from->format('d/m/Y'),
            $leaveRequest->date_to->format('d/m/Y')
        );
        $this->dispatchToMany($approverIds, 'leave_created', 'Đơn xin nghỉ mới cần duyệt', $approverBody, $data);

        $creatorUserId = $leaveRequest->employee?->user_id;
        if ($creatorUserId && ! $approverIds->contains($creatorUserId)) {
            $creatorBody = sprintf('Đơn xin nghỉ %s của bạn đã được gửi đi và đang chờ phê duyệt', $leaveRequest->code);
            $this->sendToUser($creatorUserId, 'leave_created', 'Đơn xin nghỉ đã gửi duyệt', $creatorBody, $data);
        }
    }

    public function notifyLeaveRequestApproved(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->loadMissing('employee');

        $data  = ['leave_request_id' => $leaveRequest->id];
        $title = 'Đơn xin nghỉ đã được duyệt';
        $body  = sprintf(
            'Đơn xin nghỉ %s (%s — %s) đã được duyệt',
            $leaveRequest->code,
            $leaveRequest->date_from->format('d/m/Y'),
            $leaveRequest->date_to->format('d/m/Y')
        );

        $recipients = $this->approverIds('approve-leave-requests')
            ->merge($this->employeeUserIds(collect([$leaveRequest->employee_id])));

        $this->dispatchToMany($recipients, 'leave_approved', $title, $body, $data, auth()->id());
    }

    public function notifyLeaveRequestRejected(LeaveRequest $leaveRequest, string $reason): void
    {
        $leaveRequest->loadMissing('employee');

        $data = ['leave_request_id' => $leaveRequest->id];
        $body = sprintf(
            'Đơn xin nghỉ %s của bạn đã bị từ chối — Lý do: %s',
            $leaveRequest->code,
            Str::limit($reason, 80)
        );

        $recipients = $this->approverIds('approve-leave-requests')
            ->merge($this->employeeUserIds(collect([$leaveRequest->employee_id])));

        $this->dispatchToMany($recipients, 'leave_rejected', 'Đơn xin nghỉ bị từ chối', $body, $data, auth()->id());
    }

    // -------------------------------------------------------------------------
    // Shift swap notifications
    // Recipients: người có quyền duyệt + người tạo (lúc gửi); + cả 2 bên khi đã duyệt/từ chối
    // -------------------------------------------------------------------------

    public function notifyShiftSwapCreated(ShiftSwapRequest $swap): void
    {
        $swap->loadMissing(['requesterEmployee', 'targetEmployee', 'requesterSchedule', 'targetSchedule']);

        $data        = ['shift_swap_request_id' => $swap->id];
        $approverIds = $this->approverIds('approve-shift-swaps');

        $approverBody = sprintf(
            '%s muốn đổi ca ngày %s với ca ngày %s của %s',
            $swap->requesterEmployee?->name ?? '—',
            $swap->requesterSchedule?->work_date?->format('d/m/Y') ?? '—',
            $swap->targetSchedule?->work_date?->format('d/m/Y') ?? '—',
            $swap->targetEmployee?->name ?? '—'
        );
        $this->dispatchToMany($approverIds, 'swap_created', 'Yêu cầu đổi ca mới cần duyệt', $approverBody, $data);

        $creatorUserId = $swap->requesterEmployee?->user_id;
        if ($creatorUserId && ! $approverIds->contains($creatorUserId)) {
            $creatorBody = sprintf('Yêu cầu đổi ca %s của bạn đã được gửi đi và đang chờ phê duyệt', $swap->code);
            $this->sendToUser($creatorUserId, 'swap_created', 'Yêu cầu đổi ca đã gửi duyệt', $creatorBody, $data);
        }
    }

    public function notifyShiftSwapApproved(ShiftSwapRequest $swap): void
    {
        $swap->loadMissing(['requesterEmployee', 'targetEmployee', 'requesterSchedule', 'targetSchedule']);

        $data  = ['shift_swap_request_id' => $swap->id];
        $title = 'Yêu cầu đổi ca đã được duyệt';
        $body  = sprintf(
            'Đổi ca %s giữa %s và %s đã được duyệt',
            $swap->code,
            $swap->requesterEmployee?->name ?? '—',
            $swap->targetEmployee?->name ?? '—'
        );

        $recipients = $this->approverIds('approve-shift-swaps')
            ->merge($this->employeeUserIds(collect([$swap->requester_employee_id, $swap->target_employee_id])));

        $this->dispatchToMany($recipients, 'swap_approved', $title, $body, $data, auth()->id());
    }

    public function notifyShiftSwapRejected(ShiftSwapRequest $swap, string $reason): void
    {
        $swap->loadMissing('requesterEmployee');

        $data = ['shift_swap_request_id' => $swap->id];
        $body = sprintf(
            'Yêu cầu đổi ca %s của bạn đã bị từ chối — Lý do: %s',
            $swap->code,
            Str::limit($reason, 80)
        );

        $recipients = $this->approverIds('approve-shift-swaps')
            ->merge($this->employeeUserIds(collect([$swap->requester_employee_id])));

        $this->dispatchToMany($recipients, 'swap_rejected', 'Yêu cầu đổi ca bị từ chối', $body, $data, auth()->id());
    }

    // -------------------------------------------------------------------------
    // Staff request notifications (Lượt chấm công / Công tác-Ra ngoài / Đi muộn về sớm /
    // Thay đổi giờ vào-ra) — cùng 1 luồng thông báo cho cả 4 loại trong module "Yêu cầu & Phê duyệt".
    // Recipients: người có quyền duyệt + chính nhân viên gửi yêu cầu.
    // -------------------------------------------------------------------------

    public function notifyStaffRequestCreated(StaffRequest $staffRequest): void
    {
        $staffRequest->loadMissing('employee');

        $data        = ['staff_request_id' => $staffRequest->id];
        $approverIds = $this->approverIds('approve-staff-requests');

        $approverBody = sprintf(
            '%s vừa gửi yêu cầu %s — %s (%s)',
            $staffRequest->employee?->name ?? '—',
            $staffRequest->typeLabel(),
            $staffRequest->work_date->format('d/m/Y'),
            $staffRequest->code
        );
        $this->dispatchToMany($approverIds, 'staff_request_created', 'Yêu cầu mới cần duyệt', $approverBody, $data);

        $creatorUserId = $staffRequest->employee?->user_id;
        if ($creatorUserId && ! $approverIds->contains($creatorUserId)) {
            $creatorBody = sprintf('Yêu cầu %s (%s) của bạn đã được gửi đi và đang chờ phê duyệt', $staffRequest->typeLabel(), $staffRequest->code);
            $this->sendToUser($creatorUserId, 'staff_request_created', 'Yêu cầu đã gửi duyệt', $creatorBody, $data);
        }
    }

    public function notifyStaffRequestApproved(StaffRequest $staffRequest): void
    {
        $staffRequest->loadMissing('employee');

        $data  = ['staff_request_id' => $staffRequest->id];
        $title = 'Yêu cầu đã được duyệt';
        $body  = sprintf('Yêu cầu %s (%s, %s) đã được duyệt', $staffRequest->typeLabel(), $staffRequest->code, $staffRequest->work_date->format('d/m/Y'));

        $recipients = $this->approverIds('approve-staff-requests')
            ->merge($this->employeeUserIds(collect([$staffRequest->employee_id])));

        $this->dispatchToMany($recipients, 'staff_request_approved', $title, $body, $data, auth()->id());
    }

    public function notifyStaffRequestRejected(StaffRequest $staffRequest, string $reason): void
    {
        $staffRequest->loadMissing('employee');

        $data = ['staff_request_id' => $staffRequest->id];
        $body = sprintf(
            'Yêu cầu %s (%s) của bạn đã bị từ chối — Lý do: %s',
            $staffRequest->typeLabel(),
            $staffRequest->code,
            Str::limit($reason, 80)
        );

        $recipients = $this->approverIds('approve-staff-requests')
            ->merge($this->employeeUserIds(collect([$staffRequest->employee_id])));

        $this->dispatchToMany($recipients, 'staff_request_rejected', 'Yêu cầu bị từ chối', $body, $data, auth()->id());
    }
}
