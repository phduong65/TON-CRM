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

    public function typeIcon(): string
    {
        return match ($this->type) {
            'penalty_created'  => 'bi-hammer',
            'penalty_approved' => 'bi-check-circle-fill',
            'penalty_rejected' => 'bi-x-circle-fill',
            'redzone_alert'    => 'bi-exclamation-triangle-fill',
            default            => 'bi-bell-fill',
        };
    }

    public function typeColor(): string
    {
        return match ($this->type) {
            'penalty_created'  => 'text-amber-500 bg-amber-50 dark:bg-amber-900/30',
            'penalty_approved' => 'text-emerald-500 bg-emerald-50 dark:bg-emerald-900/30',
            'penalty_rejected' => 'text-red-500 bg-red-50 dark:bg-red-900/30',
            'redzone_alert'    => 'text-red-600 bg-red-50 dark:bg-red-900/30',
            default            => 'text-pcrm-500 bg-pcrm-50 dark:bg-pcrm-900/30',
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'penalty_created'  => 'Phiếu phạt mới',
            'penalty_approved' => 'Phiếu phạt duyệt',
            'penalty_rejected' => 'Phiếu phạt từ chối',
            'redzone_alert'    => 'Cảnh báo Redzone',
            default            => 'Thông báo chung',
        };
    }
}
