<?php

use App\Console\Commands\CheckConsecutiveRedzone;
use App\Console\Commands\GenerateRecurringShiftSchedules;
use App\Console\Commands\ResetMonthlyScores;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled tasks ──────────────────────────────────────────────────────────

// 1. Reset (initialize) monthly scores on the 1st of every month at midnight
Schedule::command(ResetMonthlyScores::class)->monthlyOn(1, '00:00');

// 2. Check for consecutive-redzone employees on the 2nd of every month
//    (runs after reset so the new month's record is already seeded)
Schedule::command(CheckConsecutiveRedzone::class)->monthlyOn(2, '08:00');

// 3. Extend active recurring fixed-shift assignments (đợt xếp ca cố định
//    không có ngày kết thúc) by rolling the generation window forward daily.
Schedule::command(GenerateRecurringShiftSchedules::class)->dailyAt('01:30');
