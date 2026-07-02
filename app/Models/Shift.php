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
     * Ca văn phòng/full-time: 1 ca chấm công đủ vào-ra = 1 công, không quy đổi theo giờ.
     * Ca part-time: công vẫn quy đổi theo standardWorkHours() (giờ làm thực tế / giờ chuẩn).
     */
    public function isFulltimeCategory(): bool
    {
        return $this->shift_type === 'fulltime';
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

        if ($this->is_overnight && $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end) - $this->break_minutes;
    }
}
