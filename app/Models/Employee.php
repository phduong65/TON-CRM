<?php

namespace App\Models;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'email',
        'phone',
        'position',
        'branch_id',
        'team_id',
        'is_active',
        'joined_at',
        'employment_type',
        'is_office',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'joined_at'  => 'date',
            'is_office'  => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EmployeeScore::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function monthlyScores(): HasMany
    {
        return $this->hasMany(MonthlyEmployeeScore::class);
    }

    public function shiftSchedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function shiftSwapRequestsSent(): HasMany
    {
        return $this->hasMany(ShiftSwapRequest::class, 'requester_employee_id');
    }

    public function shiftSwapRequestsReceived(): HasMany
    {
        return $this->hasMany(ShiftSwapRequest::class, 'target_employee_id');
    }

    public function getTotalScoreAttribute(): int
    {
        return (int) $this->scores()->sum('points');
    }

    public function getMonthlyScore(int $month, int $year): int
    {
        $record = $this->monthlyScores()
            ->where('month', $month)
            ->where('year', $year)
            ->first();
        $default = (int) Setting::getValue('default_score_per_month', 100);
        return $record ? $record->final_score : $default;
    }

    public function getCurrentZone(): string
    {
        $record = $this->monthlyScores()
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();
        return $record ? $record->zone : 'green';
    }

    /**
     * Admin / Director tài khoản không thuộc diện chấm điểm kỷ luật nội bộ —
     * không xuất hiện trong bảng xếp hạng, không bị trừ điểm khi bị báo cáo/xử phạt.
     */
    public function isExemptFromScoring(): bool
    {
        return $this->user?->hasRole(['admin', 'director']) ?? false;
    }

    /**
     * Phép năm chỉ áp dụng cho nhân viên chính thức (full_time) và thuộc khối văn phòng.
     */
    public function isEligibleForAnnualLeave(): bool
    {
        return $this->employment_type === 'full_time' && $this->is_office;
    }
}
