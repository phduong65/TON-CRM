<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Gộp 4 loại yêu cầu trong module "Yêu cầu và Phê duyệt" chưa có bảng riêng:
 * Lượt chấm công, Công tác/Ra ngoài, Đi muộn về sớm, Thay đổi giờ vào/ra.
 * Nghỉ phép (LeaveRequest) và Đổi ca làm (ShiftSwapRequest) vẫn dùng bảng/luồng riêng.
 */
class StaffRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'employee_id',
        'type',
        'work_date',
        'payload',
        'reason',
        'status',
        'approval_outcome',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'work_date'   => 'date:Y-m-d',
            'payload'     => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'attendance_correction' => 'Lượt chấm công',
            'business_trip'         => 'Công tác/Ra ngoài',
            'late_early'            => 'Đi muộn về sớm',
            'time_change'           => 'Thay đổi giờ vào/ra',
            default                 => $this->type,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'  => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối',
            default    => $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending'  => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default    => 'badge-neutral',
        };
    }

    /**
     * Mô tả ngắn gọn nội dung yêu cầu (dùng cho cột "Nội dung" trong danh sách gộp).
     */
    public function summary(): string
    {
        $p = $this->payload ?? [];

        return match ($this->type) {
            'attendance_correction' => collect([
                    !empty($p['check_in_at']) ? 'Vào: ' . $p['check_in_at'] : null,
                    !empty($p['check_out_at']) ? 'Ra: ' . $p['check_out_at'] : null,
                ])->filter()->implode(' · ') ?: '—',
            'business_trip' => trim(($p['from_time'] ?? '') . '–' . ($p['to_time'] ?? '') . (!empty($p['location']) ? ' · ' . $p['location'] : '')),
            'late_early'    => ($p['mode'] ?? '') === 'early'
                ? 'Về sớm ' . ($p['minutes'] ?? 0) . ' phút'
                : 'Đến muộn ' . ($p['minutes'] ?? 0) . ' phút',
            'time_change' => 'Giờ vào/ra mới: ' . ($p['new_check_in'] ?? '—') . ' – ' . ($p['new_check_out'] ?? '—'),
            default       => '—',
        };
    }
}
