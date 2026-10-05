<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'level',
        'status',
        'priority',
        'timezone',
        'start_at',
        'end_at',
        'scope',
        'audience',
        'config',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'level'      => 'integer',
            'priority'   => 'integer',
            'start_at'   => 'datetime',
            'end_at'     => 'datetime',
            'scope'      => 'array',
            'config'     => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ThemeAuditLog::class)->orderByDesc('occurred_at');
    }

    public function isCurrentlyActive(?Carbon $now = null): bool
    {
        if (!in_array($this->status, ['active', 'scheduled'], true)) {
            return false;
        }

        $now = $now ?? Carbon::now($this->timezone ?? 'Asia/Ho_Chi_Minh');

        if ($this->start_at && $now->lt($this->start_at)) {
            return false;
        }

        if ($this->end_at && $now->gt($this->end_at)) {
            return false;
        }

        return true;
    }

    public function getLevelLabel(): string
    {
        return match ((int)$this->level) {
            1 => 'Corporate',
            2 => 'Celebration',
            3 => 'Company Event',
            default => 'Standard',
        };
    }

    public function getLevelBadgeClass(): string
    {
        return match ((int)$this->level) {
            1 => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
            2 => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
            3 => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
            default => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'active'    => 'Đang chạy',
            'scheduled' => 'Đã lên lịch',
            'draft'     => 'Bản nháp',
            'paused'    => 'Tạm dừng',
            'archived'  => 'Đã lưu trữ',
            default     => $this->status,
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'active'    => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
            'scheduled' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'draft'     => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            'paused'    => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'archived'  => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
            default     => 'bg-slate-100 text-slate-800',
        };
    }
}
