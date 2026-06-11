<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reward extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'reward_type_id',
        'employee_id',
        'description',
        'total_points_awarded',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'approved_at'          => 'datetime',
            'total_points_awarded' => 'integer',
        ];
    }

    public function rewardType(): BelongsTo
    {
        return $this->belongsTo(RewardType::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
        return $this->hasMany(RewardMember::class);
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
