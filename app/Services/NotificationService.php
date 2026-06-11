<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Penalty;
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
}
