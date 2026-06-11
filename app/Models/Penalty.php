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
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'total_points_deducted' => 'integer',
            'total_money_deducted' => 'decimal:2',
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
}
