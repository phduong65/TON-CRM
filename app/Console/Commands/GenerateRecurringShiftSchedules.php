<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\ShiftScheduleRecurrence;
use App\Services\ShiftScheduleGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringShiftSchedules extends Command
{
    protected $signature = 'shift-schedules:generate-recurring';

    protected $description = 'Extend active recurring fixed-shift assignments by generating the next rolling window of shift_schedules rows';

    public function handle(ShiftScheduleGenerator $generator): int
    {
        $target = now()->addWeeks(ShiftScheduleGenerator::HORIZON_WEEKS)->startOfDay();
        $recurrences = ShiftScheduleRecurrence::where('is_active', true)->get();

        if ($recurrences->isEmpty()) {
            $this->info('No active recurring shift schedules to extend.');
            return self::SUCCESS;
        }

        foreach ($recurrences as $recurrence) {
            $lastThrough = $recurrence->last_generated_through
                ? Carbon::parse($recurrence->last_generated_through)
                : Carbon::parse($recurrence->starts_on)->subDay();

            $from = $lastThrough->copy()->addDay();

            if ($from->greaterThan($target)) {
                continue;
            }

            $employees = Employee::whereIn('id', $recurrence->employee_ids)->where('is_active', true)->get()->keyBy('id');
            if ($employees->isEmpty()) {
                $recurrence->update(['last_generated_through' => $target->toDateString()]);
                continue;
            }

            $result = $generator->generateRange(
                $employees,
                $recurrence->weekdays,
                $from,
                $target,
                $recurrence->shift_ids,
                $recurrence->batch_id,
                $recurrence->created_by,
            );

            $recurrence->update(['last_generated_through' => $target->toDateString()]);

            $this->info("Batch {$recurrence->batch_id}: +{$result['created']} created, {$result['skipped']} skipped.");
        }

        return self::SUCCESS;
    }
}
