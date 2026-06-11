<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\MonthlyEmployeeScore;
use App\Models\Setting;
use Illuminate\Console\Command;

class ResetMonthlyScores extends Command
{
    protected $signature = 'scores:reset-monthly
                            {--month= : Target month 1-12 (defaults to current month)}
                            {--year=  : Target year (defaults to current year)}
                            {--force  : Re-create records even if they already exist}';

    protected $description = 'Initialize monthly score records for all active employees';

    public function handle(): int
    {
        $month = (int) ($this->option('month') ?? now()->month);
        $year  = (int) ($this->option('year')  ?? now()->year);
        $force = $this->option('force');

        if ($month < 1 || $month > 12) {
            $this->error('Month must be between 1 and 12.');
            return self::FAILURE;
        }

        $initialScore = (int) Setting::getValue('default_score_per_month', 100);
        $employees    = Employee::where('is_active', true)->get();
        $created      = 0;
        $skipped      = 0;

        $this->info("Initializing monthly scores for {$month}/{$year} — {$employees->count()} active employees.");

        foreach ($employees as $emp) {
            $existing = MonthlyEmployeeScore::where([
                'employee_id' => $emp->id,
                'month'       => $month,
                'year'        => $year,
            ])->first();

            if ($existing && !$force) {
                $skipped++;
                continue;
            }

            if ($existing && $force) {
                $existing->update([
                    'initial_score'   => $initialScore,
                    'deducted_points' => 0,
                    'final_score'     => $initialScore,
                    'zone'            => 'green',
                ]);
            } else {
                MonthlyEmployeeScore::create([
                    'employee_id'     => $emp->id,
                    'month'           => $month,
                    'year'            => $year,
                    'initial_score'   => $initialScore,
                    'deducted_points' => 0,
                    'final_score'     => $initialScore,
                    'zone'            => 'green',
                ]);
            }
            $created++;
        }

        $this->info("Done. Created/updated: {$created}. Skipped (already exist): {$skipped}.");
        $this->line("  → Run again with --force to reset existing records.");

        return self::SUCCESS;
    }
}
