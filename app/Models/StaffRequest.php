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
        'reversal_data',
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
            'work_date'     => 'date:Y-m-d',
            'payload'       => 'array',
            'reversal_data' => 'array',
            'reviewed_at'   => 'datetime',
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
            'overtime'              => 'Tăng ca',
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
     * Nhãn phụ hiển thị cạnh "Đã duyệt" cho 2 loại yêu cầu có thể ảnh hưởng tới trễ/sớm —
     * "Đã tha lỗi" (full_credit=true — chỉ xảy ra khi late_early duyệt với kết quả "Công thường":
     * late_minutes/early_minutes bị xoá về 0 nên KHÔNG tính kỷ luật/nhắc nhở, nhưng KHÔNG ảnh hưởng
     * tới công — công vẫn luôn tính theo giờ chấm công thực tế, xem AttendanceLog::computeCong())
     * hoặc "Ghi nhận" (đã duyệt nhưng KHÔNG tha lỗi — AttendanceLog vẫn giữ nguyên trễ/sớm thực tế,
     * chỉ là đã có đơn xin phép được ghi nhận). Không phải trạng thái lưu riêng — suy ra từ type +
     * approval_outcome đã có sẵn trên chính StaffRequest.
     */
    public function correctionOutcomeLabel(): ?string
    {
        if ($this->status !== 'approved' || !in_array($this->type, ['late_early', 'time_change'], true)) {
            return null;
        }

        return ($this->type === 'late_early' && $this->approval_outcome === 'normal')
            ? 'Đã tha lỗi'
            : 'Ghi nhận';
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
            'overtime'    => trim(($p['from_time'] ?? '') . '–' . ($p['to_time'] ?? ''))
                . ($this->isOvernightOvertime() ? ' (qua đêm)' : '') . ' (' . $this->overtimeHours() . 'h)',
            default       => '—',
        };
    }

    /**
     * True nếu "Đến giờ" <= "Từ giờ" — tăng ca kéo dài qua ngày hôm sau (VD 23:00–03:00).
     * Không có trường hợp hợp lệ nào to_time <= from_time trong cùng 1 ngày, nên coi đây luôn
     * là qua đêm thay vì lỗi nhập liệu (xem StoreStaffRequestRequest — validation cũng cho phép).
     */
    public function isOvernightOvertime(): bool
    {
        $p = $this->payload ?? [];
        if (empty($p['from_time']) || empty($p['to_time'])) {
            return false;
        }

        return \Carbon\Carbon::parse($p['to_time'])->lessThanOrEqualTo(\Carbon\Carbon::parse($p['from_time']));
    }

    /**
     * Số giờ tăng ca tính từ from_time/to_time trong payload — dùng cho summary() và khi duyệt
     * (StaffRequestsController::applyOvertime()). Tự nhận diện qua đêm: nếu to_time <= from_time
     * thì cộng thêm 1 ngày vào to_time trước khi tính (xem isOvernightOvertime()).
     */
    public function overtimeHours(): float
    {
        $p = $this->payload ?? [];
        if (empty($p['from_time']) || empty($p['to_time'])) {
            return 0.0;
        }

        $from = \Carbon\Carbon::parse($p['from_time']);
        $to   = \Carbon\Carbon::parse($p['to_time']);
        if ($to->lessThanOrEqualTo($from)) {
            $to->addDay();
        }

        return round($from->diffInMinutes($to) / 60, 2);
    }
}
