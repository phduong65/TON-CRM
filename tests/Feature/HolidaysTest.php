<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Team;
use App\Models\User;
use App\Services\AttendanceTimesheetBuilder;
use App\Services\HolidayApplicationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HolidaysTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'manager']);
        foreach (['view-holidays', 'create-holidays', 'edit-holidays', 'delete-holidays'] as $perm) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
    }

    public function test_manager_can_view_holiday_list(): void
    {
        $response = $this->actingAs($this->manager)->get(route('holidays.index'));
        $response->assertStatus(200);
    }

    public function test_manager_can_create_holiday(): void
    {
        $response = $this->actingAs($this->manager)->post(route('holidays.store'), [
            'date'           => '2026-09-02',
            'name'           => 'Quốc khánh',
            'applies_to_all' => '1',
            'is_paid'        => '1',
            'bonus_amount'   => 500000,
        ]);

        $response->assertRedirect(route('holidays.index'));
        $this->assertDatabaseHas('holidays', ['date' => '2026-09-02', 'name' => 'Quốc khánh', 'is_paid' => true]);
    }

    public function test_manager_can_update_holiday(): void
    {
        $holiday = Holiday::create(['date' => '2026-01-01', 'name' => 'Tết dương lịch', 'is_paid' => true]);

        $response = $this->actingAs($this->manager)->put(route('holidays.update', $holiday), [
            'date' => '2026-01-01',
            'name' => 'Tết dương lịch (sửa)',
            'applies_to_all' => '1',
            'is_paid' => '1',
        ]);

        $response->assertRedirect(route('holidays.index'));
        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'name' => 'Tết dương lịch (sửa)']);
    }

    public function test_manager_can_deactivate_holiday(): void
    {
        $holiday = Holiday::create(['date' => '2026-04-30', 'name' => 'Giải phóng miền Nam', 'is_paid' => true]);

        $response = $this->actingAs($this->manager)->delete(route('holidays.destroy', $holiday));

        $response->assertRedirect();
        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'is_active' => false]);
    }

    public function test_duplicate_date_is_rejected(): void
    {
        Holiday::create(['date' => '2026-05-01', 'name' => 'Quốc tế lao động', 'is_paid' => true]);

        $response = $this->actingAs($this->manager)->post(route('holidays.store'), [
            'date' => '2026-05-01',
            'name' => 'Trùng ngày',
            'is_paid' => '1',
        ]);

        $response->assertSessionHasErrors('date');
    }

    /**
     * Dựng 1 chi nhánh với 2 bộ phận: Văn phòng (is_office) + Nhà hàng, mỗi bộ phận 1 NV có ca vào
     * ngày $date. Trả về [officeEmployee, restaurantEmployee, officeSchedule, restaurantSchedule].
     */
    private function seedOfficeAndRestaurant(string $date): array
    {
        $branch = Branch::create(['code' => 'BR-1', 'name' => 'CN 1', 'is_active' => true]);
        $officeTeam     = Team::create(['code' => 'VP', 'name' => 'Văn Phòng', 'branch_id' => $branch->id, 'is_office' => true, 'is_active' => true]);
        $restaurantTeam = Team::create(['code' => 'NH', 'name' => 'Nhà hàng', 'branch_id' => $branch->id, 'is_office' => false, 'is_active' => true]);

        $officeEmp = Employee::create(['code' => 'EMP-VP', 'name' => 'NV Văn phòng', 'branch_id' => $branch->id, 'team_id' => $officeTeam->id, 'is_office' => true, 'is_active' => true, 'employment_type' => 'full_time']);
        $restEmp   = Employee::create(['code' => 'EMP-NH', 'name' => 'NV Nhà hàng', 'branch_id' => $branch->id, 'team_id' => $restaurantTeam->id, 'is_office' => false, 'is_active' => true, 'employment_type' => 'full_time']);

        $officeShift = Shift::create(['code' => 'CA-VP', 'name' => 'Ca văn phòng', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
        $restShift   = Shift::create(['code' => 'CA-NH', 'name' => 'Ca nhà hàng', 'start_time' => '11:00', 'end_time' => '22:00', 'work_mode' => 'onsite']);

        $officeSchedule = ShiftSchedule::create(['employee_id' => $officeEmp->id, 'shift_id' => $officeShift->id, 'work_date' => $date, 'status' => 'scheduled', 'assignment_type' => 'rotation']);
        $restSchedule   = ShiftSchedule::create(['employee_id' => $restEmp->id, 'shift_id' => $restShift->id, 'work_date' => $date, 'status' => 'scheduled', 'assignment_type' => 'rotation']);

        return [$officeEmp, $restEmp, $officeSchedule, $restSchedule, $officeTeam, $restaurantTeam];
    }

    private function weekdayHoliday(): Carbon
    {
        // Ngày lễ rơi vào thứ Tư (không phải ngày nghỉ tuần của cả office lẫn nhà hàng).
        return Carbon::create(2026, 9, 1)->next(Carbon::WEDNESDAY);
    }

    public function test_office_scoped_holiday_cancels_office_shift_and_credits_nl_while_restaurant_works(): void
    {
        $date = $this->weekdayHoliday();
        [$officeEmp, $restEmp, $officeSchedule, $restSchedule, $officeTeam] = $this->seedOfficeAndRestaurant($date->toDateString());

        $this->actingAs($this->manager)->post(route('holidays.store'), [
            'date'           => $date->toDateString(),
            'name'           => 'Lễ chỉ khối văn phòng',
            'applies_to_all' => '0',
            'team_ids'       => [$officeTeam->id],
            'is_paid'        => '1',
            'bonus_amount'   => 200000,
        ])->assertRedirect(route('holidays.index'));

        // Office: ca bị huỷ (đánh dấu holiday_id) + có bản ghi chấm công nghỉ lễ.
        $officeSchedule->refresh();
        $this->assertEquals('cancelled', $officeSchedule->status);
        $this->assertNotNull($officeSchedule->holiday_id);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $officeEmp->id, 'work_date' => $date->toDateString(), 'source' => 'holiday',
        ]);

        // Nhà hàng: KHÔNG bị ảnh hưởng — vẫn đi làm bình thường.
        $restSchedule->refresh();
        $this->assertEquals('scheduled', $restSchedule->status);
        $this->assertNull($restSchedule->holiday_id);
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $restEmp->id, 'source' => 'holiday']);

        // Báo cáo chấm công: office = "1, NL"; nhà hàng KHÔNG phải NL (ngày làm bình thường).
        $data = app(AttendanceTimesheetBuilder::class)->build($date->copy()->startOfMonth(), $date->copy()->endOfMonth(), null, null, null);
        $idx = $date->day - 1;
        $officeRow = collect($data['rows'])->firstWhere(fn($r) => $r['employee']->id === $officeEmp->id);
        $restRow   = collect($data['rows'])->firstWhere(fn($r) => $r['employee']->id === $restEmp->id);
        $this->assertEquals('1, NL', $officeRow['day_cells'][$idx]);
        $this->assertNotEquals('1, NL', $restRow['day_cells'][$idx]);
        $this->assertEquals(1, $officeRow['summary']['holiday_days']);
        $this->assertEquals(0, $restRow['summary']['holiday_days']);
    }

    public function test_deactivating_holiday_restores_office_shift_and_removes_holiday_log(): void
    {
        $date = $this->weekdayHoliday();
        [$officeEmp, , $officeSchedule, , $officeTeam] = $this->seedOfficeAndRestaurant($date->toDateString());

        $this->actingAs($this->manager)->post(route('holidays.store'), [
            'date' => $date->toDateString(), 'name' => 'Lễ VP', 'applies_to_all' => '0',
            'team_ids' => [$officeTeam->id], 'is_paid' => '1',
        ])->assertRedirect();

        $holiday = Holiday::firstOrFail();
        $this->assertEquals('cancelled', $officeSchedule->fresh()->status);

        $this->actingAs($this->manager)->delete(route('holidays.destroy', $holiday))->assertRedirect();

        // Khôi phục ca + xoá bản ghi chấm công lễ.
        $officeSchedule->refresh();
        $this->assertEquals('scheduled', $officeSchedule->status);
        $this->assertNull($officeSchedule->holiday_id);
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $officeEmp->id, 'source' => 'holiday']);
        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'is_active' => false]);
    }

    public function test_employee_who_worked_on_holiday_keeps_shift_and_gets_no_nl_log(): void
    {
        $date = $this->weekdayHoliday();
        [$officeEmp, , $officeSchedule, , $officeTeam] = $this->seedOfficeAndRestaurant($date->toDateString());

        // NV văn phòng vẫn đi làm ngày lễ (đã chấm công) -> không huỷ ca, không tạo NL log.
        AttendanceLog::create([
            'employee_id' => $officeEmp->id, 'shift_schedule_id' => $officeSchedule->id, 'work_date' => $date->toDateString(),
            'check_in_at' => $date->toDateString() . ' 08:00:00', 'check_out_at' => $date->toDateString() . ' 17:00:00',
        ]);

        $this->actingAs($this->manager)->post(route('holidays.store'), [
            'date' => $date->toDateString(), 'name' => 'Lễ VP', 'applies_to_all' => '0',
            'team_ids' => [$officeTeam->id], 'is_paid' => '1',
        ])->assertRedirect();

        $officeSchedule->refresh();
        $this->assertEquals('scheduled', $officeSchedule->status);
        $this->assertDatabaseMissing('attendance_logs', ['employee_id' => $officeEmp->id, 'source' => 'holiday']);
    }

    public function test_user_without_permission_cannot_view_holidays(): void
    {
        $noPermUser = User::factory()->create();
        $response = $this->actingAs($noPermUser)->get(route('holidays.index'));
        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('holidays.index'));
        $response->assertRedirect(route('login'));
    }
}
