<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeReport;
use App\Models\Notification;
use App\Models\Penalty;
use App\Models\Reward;
use App\Models\User;
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

    public function notifyPenaltyCreated(Penalty $penalty): void
    {
        $penalty->loadMissing(['violation', 'employee']);

        $body = sprintf(
            '%s vừa tạo phiếu phạt %s — NV: %s — Vi phạm: %s',
            auth()->user()->name,
            $penalty->code ?? '#' . $penalty->id,
            $penalty->employee?->name ?? '—',
            $penalty->violation?->name ?? '—'
        );

        $this->sendToUsersWithPermission(
            'approve-penalties',
            'penalty_created',
            'Phiếu phạt mới cần duyệt',
            $body,
            ['penalty_id' => $penalty->id]
        );
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

        // Notify creator
        if ($penalty->created_by && $penalty->created_by !== auth()->id()) {
            $this->sendToUser($penalty->created_by, 'penalty_approved', $title, $body, $data);
        }

        // Notify penalized employees (via linked user_id)
        $employeeIds = $penalty->members->pluck('employee_id')
            ->push($penalty->employee_id)
            ->unique()
            ->filter();

        $affectedUserIds = Employee::whereIn('id', $employeeIds)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        foreach ($affectedUserIds as $userId) {
            if ($userId !== auth()->id() && $userId !== $penalty->created_by) {
                $this->sendToUser($userId, 'penalty_approved', $title, $body, $data);
            }
        }
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

        if ($penalty->created_by && $penalty->created_by !== auth()->id()) {
            $this->sendToUser(
                $penalty->created_by,
                'penalty_rejected',
                'Phiếu phạt bị từ chối',
                $body,
                ['penalty_id' => $penalty->id]
            );
        }
    }

    public function notifyRewardCreated(Reward $reward): void
    {
        $reward->loadMissing(['rewardType', 'employee']);

        $body = sprintf(
            '%s vừa tạo phiếu thưởng %s — NV: %s — Loại: %s — +%s điểm',
            auth()->user()->name,
            $reward->code,
            $reward->employee?->name ?? '—',
            $reward->rewardType?->name ?? '—',
            number_format($reward->total_points_awarded)
        );

        $this->sendToUsersWithPermission(
            'approve-rewards',
            'reward_created',
            'Phiếu thưởng mới cần duyệt',
            $body,
            ['reward_id' => $reward->id]
        );
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

        // Notify creator
        if ($reward->created_by && $reward->created_by !== auth()->id()) {
            $this->sendToUser($reward->created_by, 'reward_approved', $title, $body, $data);
        }

        // Notify rewarded employees (via linked user_id)
        $employeeIds = $reward->members->pluck('employee_id')
            ->push($reward->employee_id)
            ->unique()
            ->filter();

        $affectedUserIds = Employee::whereIn('id', $employeeIds)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        foreach ($affectedUserIds as $userId) {
            if ($userId !== auth()->id() && $userId !== $reward->created_by) {
                $this->sendToUser($userId, 'reward_approved', $title, $body, $data);
            }
        }
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

        if ($reward->created_by && $reward->created_by !== auth()->id()) {
            $this->sendToUser(
                $reward->created_by,
                'reward_rejected',
                'Phiếu thưởng bị từ chối',
                $body,
                ['reward_id' => $reward->id]
            );
        }
    }

    public function notifyReportCreated(EmployeeReport $report): void
    {
        $report->loadMissing(['reporter', 'reported', 'violation']);

        $body = sprintf(
            '%s vừa tạo báo cáo %s — Báo cáo: %s — Vi phạm: %s',
            auth()->user()->name,
            $report->code,
            $report->reported?->name ?? '—',
            $report->violation?->name ?? 'Không xác định'
        );

        $this->sendToUsersWithPermission(
            'approve-reports',
            'report_created',
            'Báo cáo mới cần duyệt',
            $body,
            ['report_id' => $report->id]
        );
    }

    public function notifyReportApproved(EmployeeReport $report): void
    {
        $report->loadMissing(['reporter', 'reported', 'violation']);

        $data = ['report_id' => $report->id];

        // Thông báo cho reporter: được cộng điểm
        if ($report->reporter?->user_id && $report->reporter->user_id !== auth()->id()) {
            $body = sprintf(
                'Báo cáo %s của bạn đã được duyệt — Cộng +%s điểm vào tài khoản',
                $report->code,
                $report->reward_points
            );
            $this->sendToUser($report->reporter->user_id, 'report_approved', 'Báo cáo được duyệt', $body, $data);
        }

        // Thông báo cho người bị báo cáo (nếu có violation points)
        if ($report->reported?->user_id && $report->reported->user_id !== auth()->id()) {
            $deducted = $report->violation?->points_deducted ?? 0;
            $body = sprintf(
                'Bạn bị báo cáo vi phạm %s — %s — %s',
                $report->violation?->name ?? 'vi phạm nội quy',
                $deducted > 0 ? "Trừ {$deducted} điểm" : 'Không trừ điểm',
                $report->code
            );
            $this->sendToUser($report->reported->user_id, 'report_approved', 'Thông báo vi phạm từ báo cáo', $body, $data);
        }
    }

    public function notifyReportRejected(EmployeeReport $report, string $reason): void
    {
        $report->loadMissing(['reporter', 'reported']);

        if ($report->reporter?->user_id && $report->reporter->user_id !== auth()->id()) {
            $body = sprintf(
                'Báo cáo %s của bạn bị từ chối — Lý do: %s',
                $report->code,
                Str::limit($reason, 80)
            );
            $this->sendToUser(
                $report->reporter->user_id,
                'report_rejected',
                'Báo cáo bị từ chối',
                $body,
                ['report_id' => $report->id]
            );
        }
    }
}
