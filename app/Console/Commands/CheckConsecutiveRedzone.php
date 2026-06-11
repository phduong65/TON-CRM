<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\MonthlyEmployeeScore;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckConsecutiveRedzone extends Command
{
    protected $signature = 'scores:check-consecutive-redzone
                            {--month= : Reference month (defaults to current month)}
                            {--year=  : Reference year (defaults to current year)}';

    protected $description = 'Detect employees in redzone for consecutive months and alert managers';

    public function handle(): int
    {
        $refMonth = (int) ($this->option('month') ?? now()->month);
        $refYear  = (int) ($this->option('year')  ?? now()->year);
        $consecutive = (int) Setting::getValue('consecutive_redzone_months', 2);

        if ($consecutive < 1) {
            $this->warn('consecutive_redzone_months setting is less than 1. Nothing to check.');
            return self::SUCCESS;
        }

        // Build list of (month, year) pairs to check — going backwards from reference
        $periods = [];
        $ref = Carbon::create($refYear, $refMonth, 1);
        for ($i = 0; $i < $consecutive; $i++) {
            $d = $ref->copy()->subMonths($i);
            $periods[] = ['month' => $d->month, 'year' => $d->year];
        }

        $employees = Employee::where('is_active', true)->get();
        $flagged   = [];

        foreach ($employees as $emp) {
            $allRed = true;
            foreach ($periods as $p) {
                $score = MonthlyEmployeeScore::where([
                    'employee_id' => $emp->id,
                    'month'       => $p['month'],
                    'year'        => $p['year'],
                ])->first();

                if (! $score || $score->zone !== 'red') {
                    $allRed = false;
                    break;
                }
            }
            if ($allRed) {
                $flagged[] = $emp;
            }
        }

        if (empty($flagged)) {
            $this->info("No employees found in redzone for {$consecutive} consecutive months.");
            return self::SUCCESS;
        }

        $this->warn(count($flagged) . " employee(s) in redzone for {$consecutive} consecutive months:");
        foreach ($flagged as $emp) {
            $this->line("  • [{$emp->code}] {$emp->name}");
        }

        // Notify all admins/managers
        $managers = User::role(['admin', 'manager'])->get();
        foreach ($flagged as $emp) {
            $body = "[{$emp->code}] {$emp->name} đã ở Redzone liên tiếp {$consecutive} tháng — cần xem xét xử phạt đặc biệt.";
            foreach ($managers as $manager) {
                Notification::create([
                    'user_id'  => $manager->id,
                    'title'    => 'Cảnh báo Redzone liên tiếp',
                    'body'     => $body,
                    'type'     => 'redzone_alert',
                    'data'     => ['employee_id' => $emp->id, 'employee_code' => $emp->code],
                ]);
            }
        }

        $this->info("Notifications sent to " . $managers->count() . " manager(s).");
        return self::SUCCESS;
    }
}
