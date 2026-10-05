<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'branch_id',
        'start_time',
        'end_time',
        'is_overnight',
        'break_minutes',
        'break_start_time',
        'grace_late_minutes',
        'grace_early_minutes',
        'standard_work_hours',
        'shift_type',
        'work_mode',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_overnight'  => 'boolean',
            'is_active'     => 'boolean',
            'break_minutes' => 'integer',
            'grace_late_minutes'  => 'integer',
            'grace_early_minutes' => 'integer',
            'standard_work_hours' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    public function isWfh(): bool
    {
        return $this->work_mode === 'wfh';
    }

    /**
     * Số giờ = 1 công chuẩn của ca này (VD: Bếp 10h, Văn phòng 8h) — dùng làm
     * mẫu số quy đổi giờ làm thực tế sang "công" trong Bảng chấm công.
     */
    public function standardWorkHours(): float
    {
        return (float) ($this->standard_work_hours ?: 8);
    }

    public function durationMinutes(): int
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end   = \Carbon\Carbon::parse($this->end_time);

        // Suy ra ca qua đêm trực tiếp từ giờ kết thúc <= giờ bắt đầu, KHÔNG chỉ dựa vào cờ
        // is_overnight — cờ này nhập tay (checkbox) nên có thể bị để sai/quên tick, khiến ca thực
        // sự qua đêm (VD 18h-24h) bị tính duration âm/0 nếu chỉ dựa vào is_overnight.
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end) - $this->break_minutes;
    }

    /**
     * Giờ bắt đầu nghỉ giữa ca dạng 'H:i' (VD '12:00'), null nếu chưa cấu hình.
     */
    public function breakStart(): ?string
    {
        return $this->break_start_time ? substr((string) $this->break_start_time, 0, 5) : null;
    }

    /**
     * Điểm chia (giờ 'H:i') để tách nửa ca theo GIỜ CÔNG thực: từ giờ vào ca cộng dồn đến khi đủ
     * 50% giờ công, BỎ QUA khoảng nghỉ giữa ca. VD ca 09:00–18:00 nghỉ 12:00–13:00 → trả '14:00'
     * (sáng 09–14 = 4h công, chiều 14–18 = 4h công). Nếu chưa cấu hình giờ nghỉ cụ thể (hoặc
     * break_minutes = 0) thì chia tại điểm giữa giờ công tính từ đầu ca (không biết vị trí nghỉ).
     * Trả null nếu thiếu giờ vào/ra ca.
     */
    public function halfDaySplitTime(): ?string
    {
        if (!$this->start_time || !$this->end_time) {
            return null;
        }

        $start = \Carbon\Carbon::parse($this->start_time);
        $end   = \Carbon\Carbon::parse($this->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $net  = max(0, $start->diffInMinutes($end) - (int) $this->break_minutes);
        $half = intdiv($net, 2);

        if (!$this->break_start_time || (int) $this->break_minutes <= 0) {
            return $start->copy()->addMinutes($half)->format('H:i');
        }

        $breakStart = \Carbon\Carbon::parse($this->break_start_time);
        if ($breakStart->lessThan($start)) {
            $breakStart->addDay(); // ca qua đêm, giờ nghỉ rơi sang ngày hôm sau
        }
        $breakEnd = $breakStart->copy()->addMinutes((int) $this->break_minutes);

        $workBeforeBreak = max(0, $start->diffInMinutes($breakStart));
        if ($workBeforeBreak >= $half) {
            return $start->copy()->addMinutes($half)->format('H:i');
        }

        $remaining = $half - $workBeforeBreak;

        return $breakEnd->copy()->addMinutes($remaining)->format('H:i');
    }

    /**
     * Số phút CÔNG thực trong khung giờ [from, to] (dạng 'H:i') — đã trừ phần trùng với khoảng
     * nghỉ giữa ca. Dùng để tính day_fraction của "nghỉ nửa ngày theo giờ" đúng theo giờ công.
     */
    public function workMinutesInWindow(string $from, string $to): int
    {
        $f = \Carbon\Carbon::parse($from);
        $t = \Carbon\Carbon::parse($to);
        if ($t->lessThanOrEqualTo($f)) {
            return 0;
        }

        $minutes = $f->diffInMinutes($t);

        if ($this->break_start_time && (int) $this->break_minutes > 0) {
            $bs = \Carbon\Carbon::parse($this->break_start_time);
            $be = $bs->copy()->addMinutes((int) $this->break_minutes);
            $overlapStart = $f->greaterThan($bs) ? $f : $bs;
            $overlapEnd   = $t->lessThan($be) ? $t : $be;
            if ($overlapEnd->greaterThan($overlapStart)) {
                $minutes -= $overlapStart->diffInMinutes($overlapEnd);
            }
        }

        return max(0, $minutes);
    }
}
