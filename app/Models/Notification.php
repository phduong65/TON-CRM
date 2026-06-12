<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data'    => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }

    public function markAsRead(): void
    {
        if ($this->isUnread()) {
            $this->update(['read_at' => now()]);
        }
    }

    public function penaltyUrl(): ?string
    {
        $id = $this->data['penalty_id'] ?? null;
        return $id ? route('penalties.show', $id) : null;
    }

    public function rewardUrl(): ?string
    {
        $id = $this->data['reward_id'] ?? null;
        return $id ? route('rewards.show', $id) : null;
    }

    public function reportUrl(): ?string
    {
        $id = $this->data['report_id'] ?? null;
        return $id ? route('reports.show', $id) : null;
    }

    public function actionUrl(): ?string
    {
        return $this->penaltyUrl() ?? $this->rewardUrl() ?? $this->reportUrl();
    }

    public function typeIcon(): string
    {
        return match ($this->type) {
            'penalty_created'  => 'bi-hammer',
            'penalty_approved' => 'bi-check-circle-fill',
            'penalty_rejected' => 'bi-x-circle-fill',
            'reward_created'   => 'bi-gift-fill',
            'reward_approved'  => 'bi-star-fill',
            'reward_rejected'  => 'bi-x-circle-fill',
            'redzone_alert'    => 'bi-exclamation-triangle-fill',
            'report_created'   => 'bi-flag-fill',
            'report_approved'  => 'bi-check2-circle',
            'report_rejected'  => 'bi-flag',
            default            => 'bi-bell-fill',
        };
    }

    public function typeColor(): string
    {
        return match ($this->type) {
            'penalty_created'  => 'text-amber-500 bg-amber-50 dark:bg-amber-900/30',
            'penalty_approved' => 'text-emerald-500 bg-emerald-50 dark:bg-emerald-900/30',
            'penalty_rejected' => 'text-red-500 bg-red-50 dark:bg-red-900/30',
            'reward_created'   => 'text-sky-500 bg-sky-50 dark:bg-sky-900/30',
            'reward_approved'  => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30',
            'reward_rejected'  => 'text-red-500 bg-red-50 dark:bg-red-900/30',
            'redzone_alert'    => 'text-red-600 bg-red-50 dark:bg-red-900/30',
            'report_created'   => 'text-violet-500 bg-violet-50 dark:bg-violet-900/30',
            'report_approved'  => 'text-emerald-500 bg-emerald-50 dark:bg-emerald-900/30',
            'report_rejected'  => 'text-red-500 bg-red-50 dark:bg-red-900/30',
            default            => 'text-pcrm-500 bg-pcrm-50 dark:bg-pcrm-900/30',
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'penalty_created'  => 'Phiếu phạt mới',
            'penalty_approved' => 'Phiếu phạt duyệt',
            'penalty_rejected' => 'Phiếu phạt từ chối',
            'reward_created'   => 'Phiếu thưởng mới',
            'reward_approved'  => 'Phiếu thưởng duyệt',
            'reward_rejected'  => 'Phiếu thưởng từ chối',
            'redzone_alert'    => 'Cảnh báo Redzone',
            'report_created'   => 'Báo cáo mới',
            'report_approved'  => 'Báo cáo duyệt',
            'report_rejected'  => 'Báo cáo từ chối',
            default            => 'Thông báo chung',
        };
    }

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            'penalty_created'  => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
            'penalty_approved' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
            'penalty_rejected' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            'reward_created'   => 'bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-400',
            'reward_approved'  => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
            'reward_rejected'  => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            'redzone_alert'    => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            'report_created'   => 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400',
            'report_approved'  => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
            'report_rejected'  => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
            default            => 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
        };
    }
}
