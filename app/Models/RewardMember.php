<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardMember extends Model
{
    protected $fillable = [
        'reward_id',
        'employee_id',
        'points_awarded',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'points_awarded' => 'integer',
        ];
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
