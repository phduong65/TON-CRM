<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetConfirmation extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'status',
        'confirmed_by',
        'confirmed_at',
        'is_proxy_confirmed',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at'        => 'datetime',
            'is_proxy_confirmed'  => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'confirmed' => 'Đã xác nhận',
            default     => 'Chưa xác nhận',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'confirmed' => 'badge-success',
            default     => 'badge-warning',
        };
    }
}
