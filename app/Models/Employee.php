<?php

namespace App\Models;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'email',
        'phone',
        'position',
        'branch_id',
        'team_id',
        'is_active',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'joined_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EmployeeScore::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function monthlyScores(): HasMany
    {
        return $this->hasMany(MonthlyEmployeeScore::class);
    }

    public function getTotalScoreAttribute(): int
    {
        return (int) $this->scores()->sum('points');
    }

    public function getMonthlyScore(int $month, int $year): int
    {
        $record = $this->monthlyScores()
            ->where('month', $month)
            ->where('year', $year)
            ->first();
        $default = (int) Setting::getValue('default_score_per_month', 100);
        return $record ? $record->final_score : $default;
    }

    public function getCurrentZone(): string
    {
        $record = $this->monthlyScores()
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();
        return $record ? $record->zone : 'green';
    }
}
