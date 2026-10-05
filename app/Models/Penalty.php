<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penalty extends Model
{
    protected $fillable = [
        'code',
        'created_by',
        'employee_id',
        'violation_id',
        'description',
        'status',
        'approved_by',
        'approved_at',
        'total_points_deducted',
        'total_money_deducted',
        'rejected_reason',
        'revoked_at',
        'revoked_by',
        'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'approved_at'          => 'datetime',
            'revoked_at'           => 'datetime',
            'total_points_deducted' => 'integer',
            'total_money_deducted'  => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(PenaltyMember::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(Appeal::class);
    }

    /**
     * Sinh mã "PNL-YYYYMM-XXXX" tiếp theo trong tháng. Penalty KHÔNG dùng SoftDeletes —
     * destroy() xoá cứng thật (xem PenaltiesController::destroy()) — nên không thể/không cần
     * withTrashed(). Dùng MAX số thứ tự đã tồn tại (không phải COUNT số dòng còn lại): nếu
     * COUNT() lại được dùng, xoá 1 phiếu ở giữa tháng sẽ làm số đếm lùi lại, sinh trùng "code"
     * (unique constraint) với phiếu chưa xoá và gây crash 500 khi insert.
     */
    public static function nextCode(): string
    {
        $prefix = 'PNL-' . now()->format('Ym') . '-';

        $maxSeq = static::where('code', 'like', $prefix . '%')
            ->get(['code'])
            ->map(fn ($p) => (int) substr($p->code, strlen($prefix)))
            ->max();

        return $prefix . str_pad((string) (($maxSeq ?? 0) + 1), 4, '0', STR_PAD_LEFT);
    }
}
