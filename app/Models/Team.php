<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'branch_id',
        'is_office',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_office' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function coverageRequirements(): HasMany
    {
        return $this->hasMany(ShiftCoverageRequirement::class);
    }

}
