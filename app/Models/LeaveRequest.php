<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'employee_id',
        'date_from',
        'date_to',
        'shift_schedule_id',
        'is_partial_day',
        'from_time',
        'to_time',
        'day_fraction',
        'type',
        'reason',
        'handover_to',
        'handover_employee_id',
        'handover_phone',
        'handover_note',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'date_from'      => 'date:Y-m-d',
            'date_to'        => 'date:Y-m-d',
            'is_partial_day' => 'boolean',
            'day_fraction'   => 'decimal:2',
            'reviewed_at'    => 'datetime',
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

    public function shiftSchedule(): BelongsTo
    {
        return $this->belongsTo(ShiftSchedule::class);
    }

    /**
     * Danh sách ca cụ thể đã chọn để nghỉ (đơn "chỉ nghỉ 1 số ca cụ thể", có thể thuộc nhiều
     * ngày khác nhau) — thay cho shift_schedule_id đơn (chỉ hỗ trợ 1 ca/1 ngày, vẫn giữ lại cho
     * dữ liệu cũ tạo trước khi có bảng leave_request_shift_schedules).
     */
    public function shiftSchedules(): BelongsToMany
    {
        return $this->belongsToMany(ShiftSchedule::class, 'leave_request_shift_schedules')
            ->withPivot('day_fraction')
            ->withTimestamps();
    }

    public function handoverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'handover_employee_id');
    }

    /**
     * Số ngày phép bị trừ vào quỹ phép năm. Nghỉ theo giờ (is_partial_day) chỉ trừ đúng
     * day_fraction (VD 0.5 ngày cho nghỉ nửa ca) thay vì tính tròn 1 ngày như nghỉ cả ngày.
     */
    public function daysCount(): float
    {
        if ($this->is_partial_day && $this->day_fraction !== null) {
            return (float) $this->day_fraction;
        }

        return (float) ($this->date_from->diffInDays($this->date_to) + 1);
    }

    /**
     * "09:00–12:00" — hiển thị khung giờ nghỉ cho đơn nghỉ theo giờ.
     */
    public function partialTimeLabel(): ?string
    {
        if (!$this->is_partial_day || !$this->from_time || !$this->to_time) {
            return null;
        }

        return substr($this->from_time, 0, 5) . '–' . substr($this->to_time, 0, 5);
    }

    /**
     * "Nghỉ 3 ca cụ thể" (kiểu mới, có thể nhiều ngày) hoặc "Nghỉ nửa ngày (09:00–12:00)" (dữ
     * liệu cũ trước khi có chọn nhiều ca — 1 ca/1 ngày, nghỉ theo giờ thủ công) — nhãn hiển thị
     * cho đơn nghỉ theo ca trên các phiếu/danh sách nghỉ phép, dùng chung để không lặp lại logic
     * ở từng view. Truyền $shiftScheduleCount nếu đã eager-load sẵn (tránh N+1 khi hiển thị danh
     * sách nhiều đơn).
     */
    public function partialDayLabel(?int $shiftScheduleCount = null): ?string
    {
        if (!$this->is_partial_day) {
            return null;
        }

        $count = $shiftScheduleCount ?? ($this->relationLoaded('shiftSchedules')
            ? $this->shiftSchedules->count()
            : $this->shiftSchedules()->count());

        if ($count > 0) {
            return "Nghỉ {$count} ca cụ thể";
        }

        $time = $this->partialTimeLabel();

        return 'Nghỉ nửa ngày' . ($time ? " ({$time})" : '');
    }

    /**
     * Các ca đã chọn nghỉ ("Nghỉ N ca cụ thể") — ưu tiên bảng pivot, fallback shift_schedule_id
     * đơn cho dữ liệu cũ. Đơn nghỉ cả ngày trả mảng rỗng. Nên eager-load
     * 'shiftSchedules.shift' và 'shiftSchedule.shift' khi dùng cho danh sách (tránh N+1).
     *
     * @return array<int, array{date: string, name: string, time: string, fraction: ?float}>
     */
    public function selectedShifts(): array
    {
        $schedules = $this->shiftSchedules->isNotEmpty()
            ? $this->shiftSchedules
            : collect([$this->shiftSchedule])->filter();

        return $schedules
            ->sortBy(fn($s) => $s->work_date->toDateString() . ' ' . ($s->effectiveShift()?->start_time ?? ''))
            ->map(function ($s) {
                $shift = $s->effectiveShift();

                return [
                    'date'     => $s->work_date->format('d/m/Y'),
                    'name'     => $s->shift?->name ?? 'Ca linh hoạt',
                    'time'     => $shift && $shift->start_time && $shift->end_time
                        ? substr($shift->start_time, 0, 5) . ' – ' . substr($shift->end_time, 0, 5)
                        : '',
                    'fraction' => isset($s->pivot) && $s->pivot->day_fraction !== null ? (float) $s->pivot->day_fraction : null,
                ];
            })
            ->values()
            ->all();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'annual' => 'Nghỉ phép năm',
            'unpaid' => 'Nghỉ không lương',
            'sick'   => 'Nghỉ ốm',
            default  => 'Khác',
        };
    }

    /**
     * Chỉ 'annual' (phép năm) được tính có lương (NC) — mọi loại còn lại (kể cả dữ liệu cũ
     * 'sick'/'other') đều tính không lương (NK). Dùng chung ở AttendanceTimesheetBuilder và
     * TimesheetConfirmationService để tránh lặp lại quy tắc này ở nhiều nơi.
     */
    public static function isPaidType(string $type): bool
    {
        return $type === 'annual';
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
}
