<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\ShiftSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendShiftAttendanceRemindersTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-07 09:00:00'));

        $branch = Branch::create(['code' => 'BR-RM', 'name' => 'Chi nhánh Reminder', 'is_active' => true]);
        $user   = User::factory()->create();
        $this->employee = Employee::create([
            'code' => 'EMP-RM', 'name' => 'NV Reminder', 'branch_id' => $branch->id, 'user_id' => $user->id, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeSchedule(string $start, string $end, bool $wfh = false): ShiftSchedule
    {
        return ShiftSchedule::create([
            'employee_id'          => $this->employee->id,
            'shift_id'             => null,
            'branch_id'            => $this->employee->branch_id,
            'work_date'            => now()->toDateString(),
            'assignment_type'      => 'fixed',
            'status'               => 'scheduled',
            'custom_start_time'    => $start,
            'custom_end_time'      => $end,
            'custom_break_minutes' => 0,
            'custom_is_overnight'  => false,
            'custom_is_wfh'        => $wfh,
        ]);
    }

    public function test_sends_checkin_reminder_within_5_minutes_before_start(): void
    {
        // now = 09:00, start = 09:03 -> within [08:58, 09:03] reminder window
        $this->makeSchedule('09:03:00', '17:00:00');

        $this->artisan('attendance:send-shift-reminders')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->employee->user_id,
            'type'    => 'shift_checkin_reminder',
        ]);
    }

    public function test_does_not_send_checkin_reminder_twice(): void
    {
        $this->makeSchedule('09:03:00', '17:00:00');

        $this->artisan('attendance:send-shift-reminders');
        $this->artisan('attendance:send-shift-reminders');

        $this->assertEquals(
            1,
            Notification::where('type', 'shift_checkin_reminder')->count()
        );
    }

    public function test_does_not_send_checkin_reminder_if_already_checked_in(): void
    {
        $schedule = $this->makeSchedule('09:03:00', '17:00:00');
        AttendanceLog::create([
            'employee_id'       => $this->employee->id,
            'shift_schedule_id' => $schedule->id,
            'work_date'         => now()->toDateString(),
            'check_in_at'       => now(),
        ]);

        $this->artisan('attendance:send-shift-reminders');

        $this->assertDatabaseMissing('notifications', ['type' => 'shift_checkin_reminder']);
    }

    public function test_sends_checkin_missing_alert_10_minutes_after_start_without_checkin(): void
    {
        // now = 09:00, start = 08:49 -> 11 minutes late, still no check-in
        $this->makeSchedule('08:49:00', '17:00:00');

        $this->artisan('attendance:send-shift-reminders');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->employee->user_id,
            'type'    => 'shift_checkin_missing',
        ]);
    }

    public function test_sends_checkout_reminder_when_checked_in_and_end_approaching(): void
    {
        // now = 09:00, end = 09:02 -> within reminder window, employee already checked in
        $schedule = $this->makeSchedule('07:00:00', '09:02:00');
        AttendanceLog::create([
            'employee_id'       => $this->employee->id,
            'shift_schedule_id' => $schedule->id,
            'work_date'         => now()->toDateString(),
            'check_in_at'       => now()->subHours(2),
        ]);

        $this->artisan('attendance:send-shift-reminders');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->employee->user_id,
            'type'    => 'shift_checkout_reminder',
        ]);
    }

    public function test_sends_checkout_missing_alert_10_minutes_after_end_without_checkout(): void
    {
        // now = 09:00, end = 08:49 -> 11 minutes late, checked in but never checked out
        $schedule = $this->makeSchedule('07:00:00', '08:49:00');
        AttendanceLog::create([
            'employee_id'       => $this->employee->id,
            'shift_schedule_id' => $schedule->id,
            'work_date'         => now()->toDateString(),
            'check_in_at'       => now()->subHours(2),
        ]);

        $this->artisan('attendance:send-shift-reminders');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->employee->user_id,
            'type'    => 'shift_checkout_missing',
        ]);
    }

    public function test_does_not_send_checkout_missing_alert_if_already_checked_out(): void
    {
        $schedule = $this->makeSchedule('07:00:00', '08:49:00');
        AttendanceLog::create([
            'employee_id'       => $this->employee->id,
            'shift_schedule_id' => $schedule->id,
            'work_date'         => now()->toDateString(),
            'check_in_at'       => now()->subHours(2),
            'check_out_at'      => now()->subMinutes(20),
        ]);

        $this->artisan('attendance:send-shift-reminders');

        $this->assertDatabaseMissing('notifications', ['type' => 'shift_checkout_missing']);
    }

    public function test_ignores_cancelled_schedules(): void
    {
        $schedule = $this->makeSchedule('08:49:00', '17:00:00');
        $schedule->update(['status' => 'cancelled']);

        $this->artisan('attendance:send-shift-reminders');

        $this->assertDatabaseMissing('notifications', ['type' => 'shift_checkin_missing']);
    }

    public function test_applies_same_reminder_rules_to_wfh_shifts(): void
    {
        $this->makeSchedule('08:49:00', '17:00:00', wfh: true);

        $this->artisan('attendance:send-shift-reminders');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->employee->user_id,
            'type'    => 'shift_checkin_missing',
        ]);
    }
}
