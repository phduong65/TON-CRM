<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'reporter_employee_id',
        'reported_employee_id',
        'violation_id',
        'description',
        'evidence_note',
        'status',
        'reward_points',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at'   => 'datetime',
            'reward_points' => 'integer',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporter_employee_id');
    }

    public function reported(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_employee_id');
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
