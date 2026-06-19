<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class MonthlyEmployeeScore extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'initial_score',
        'deducted_points',
        'rewarded_points',
        'surplus_points',
        'final_score',
        'zone',
    ];

    protected function casts(): array
    {
        return [
            'month'            => 'integer',
            'year'             => 'integer',
            'initial_score'    => 'integer',
            'deducted_points'  => 'integer',
            'rewarded_points'  => 'integer',
            'surplus_points'   => 'integer',
            'final_score'      => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public static function computeZone(int $score): string
    {
        $greenMin  = (int) Setting::getValue('greenzone_min', 90);
        $yellowMin = (int) Setting::getValue('yellowzone_min', 80);
        $orangeMin = (int) Setting::getValue('orangezone_min', 70);

        if ($score >= $greenMin)  return 'green';
        if ($score >= $yellowMin) return 'yellow';
        if ($score >= $orangeMin) return 'orange';
        return 'red';
    }

    public static function zoneLabel(string $zone): string
    {
        return match ($zone) {
            'green'  => 'Greenzone',
            'yellow' => 'Yellowzone',
            'orange' => 'Orangezone',
            'red'    => 'Redzone',
            default  => $zone,
        };
    }

    public static function zoneColor(string $zone): string
    {
        return match ($zone) {
            'green'  => 'emerald',
            'yellow' => 'yellow',
            'orange' => 'orange',
            'red'    => 'red',
            default  => 'slate',
        };
    }

    public static function zoneBadgeClass(string $zone): string
    {
        return match ($zone) {
            'green'  => 'badge-success',
            'yellow' => 'badge-warning',
            'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            'red'    => 'badge-danger',
            default  => 'badge-neutral',
        };
    }

    /**
     * Ensure a monthly record exists for the given employee/month/year.
     * Returns the existing or newly created record.
     */
    public static function ensureExists(int $employeeId, int $month, int $year): self
    {
        $initialScore = (int) Setting::getValue('default_score_per_month', 100);

        return static::firstOrCreate(
            ['employee_id' => $employeeId, 'month' => $month, 'year' => $year],
            [
                'initial_score'   => $initialScore,
                'deducted_points' => 0,
                'rewarded_points' => 0,
                'surplus_points'  => 0,
                'final_score'     => $initialScore,
                'zone'            => 'green',
            ]
        );
    }

    public function deduct(int $points): void
    {
        DB::transaction(function () use ($points) {
            $fresh = static::lockForUpdate()->findOrFail($this->id);
            $fresh->deducted_points += $points;
            // Deductions always come from the base score (final_score), never from surplus_points
            $fresh->final_score = max(0, $fresh->final_score - $points);
            $fresh->zone        = static::computeZone($fresh->final_score);
            $fresh->save();
            $this->fill($fresh->getAttributes());
        });
    }

    public function reward(int $points): void
    {
        DB::transaction(function () use ($points) {
            $fresh = static::lockForUpdate()->findOrFail($this->id);
            $fresh->rewarded_points += $points;
            // Rewards first fill the base score up to initial_score (cap); excess goes to surplus_points
            $pointsForBase      = min($points, max(0, $fresh->initial_score - $fresh->final_score));
            $fresh->final_score = min($fresh->initial_score, $fresh->final_score + $pointsForBase);
            $fresh->surplus_points += ($points - $pointsForBase);
            $fresh->zone        = static::computeZone($fresh->final_score);
            $fresh->save();
            $this->fill($fresh->getAttributes());
        });
    }

    /**
     * Reverse a deduction (revoke penalty): add points back to final_score up to cap, excess to surplus.
     */
    public function refundDeduction(int $points): void
    {
        DB::transaction(function () use ($points) {
            $fresh = static::lockForUpdate()->findOrFail($this->id);
            $fresh->deducted_points = max(0, $fresh->deducted_points - $points);
            $capacity              = max(0, $fresh->initial_score - $fresh->final_score);
            $pointsForBase         = min($points, $capacity);
            $fresh->final_score    = min($fresh->initial_score, $fresh->final_score + $pointsForBase);
            $fresh->surplus_points += ($points - $pointsForBase);
            $fresh->zone           = static::computeZone($fresh->final_score);
            $fresh->save();
            $this->fill($fresh->getAttributes());
        });
    }

    /**
     * Reverse a reward (revoke reward): remove from surplus first, then from final_score.
     */
    public function revokeReward(int $points): void
    {
        DB::transaction(function () use ($points) {
            $fresh = static::lockForUpdate()->findOrFail($this->id);
            $fresh->rewarded_points = max(0, $fresh->rewarded_points - $points);
            // Take from surplus first (the overflow beyond initial_score), then from final_score
            $fromSurplus           = min($points, $fresh->surplus_points);
            $fresh->surplus_points -= $fromSurplus;
            $fromBase              = $points - $fromSurplus;
            $fresh->final_score    = max(0, $fresh->final_score - $fromBase);
            $fresh->zone           = static::computeZone($fresh->final_score);
            $fresh->save();
            $this->fill($fresh->getAttributes());
        });
    }
}
