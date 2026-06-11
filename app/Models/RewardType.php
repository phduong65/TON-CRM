<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardType extends Model
{
    protected $fillable = [
        'reward_category_id',
        'name',
        'description',
        'default_points',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'default_points' => 'integer',
            'is_active'      => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RewardCategory::class, 'reward_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
