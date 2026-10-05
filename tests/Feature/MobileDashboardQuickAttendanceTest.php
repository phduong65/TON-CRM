<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Widget chấm công nhanh trên dashboard MOBILE đọc nhầm check_in_at/check_out_at/late_minutes
 * trực tiếp trên ShiftSchedule (cột không tồn tại trên bảng này — dữ liệu thật nằm ở quan hệ
 * attendanceLog trỏ tới AttendanceLog) khiến trạng thái Vào ca/Ra ca luôn hiển thị "chưa check-in"
 * dù đã chấm công thật. Test này khoá lại hành vi đúng sau khi sửa (xem mobile/dashboard/index.blade.php).
 */
class MobileDashboardQuickAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;
    private Branch $branch;

    private const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'staff']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'checkin-attendance']));

        $this->user = User::factory()->create();
        $this->user->assignRole('staff');

        $this->branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->employee = Employee::create([
            'code'      => 'EMP-01',
            'name'      => 'Nguyễn Văn A',
            'user_id'   => $this->user->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function getMobileDashboard()
    {
        return $this->actingAs($this->user)
            ->withSession(['view_mode' => 'mobile'])
            ->withHeaders(['User-Agent' => self::MOBILE_UA])
            ->get(route('dashboard'));
    }

    private function makeOnsiteShiftToday(): ShiftSchedule
    {
        $shift = Shift::create([
            'code' => 'CA-HC', 'name' => 'Ca hành chính',
            'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);

        return ShiftSchedule::create([
            'employee_id'      => $this->employee->id,
            'shift_id'         => $shift->id,
            'branch_id'        => $this->branch->id,
            'work_date'        => now()->toDateString(),
            'assignment_type'  => 'rotation',
            'status'           => 'scheduled',
        ]);
    }

    public function test_checkin_button_active_before_checkin(): void
    {
        $schedule = $this->makeOnsiteShiftToday();

        $response = $this->getMobileDashboard();

        $response->assertOk();
        $response->assertSee('id="btnCheckIn-' . $schedule->id . '"', false);
        $response->assertDontSee('Đã vào ca');
    }

    public function test_checkin_state_reflects_real_attendance_log_after_checkin(): void
    {
        $schedule = $this->makeOnsiteShiftToday();

        AttendanceLog::create([
            'employee_id'       => $this->employee->id,
            'shift_schedule_id' => $schedule->id,
            'work_date'         => now()->toDateString(),
            'check_in_at'       => now(),
            'check_in_method'   => 'gps_ip',
        ]);

        $response = $this->getMobileDashboard();

        $response->assertOk();
        $response->assertSee('Đã vào ca');
        $response->assertDontSee('id="btnCheckIn-' . $schedule->id . '"', false);
        $response->assertSee('id="btnCheckOut-' . $schedule->id . '"', false);
    }

    public function test_both_states_show_done_and_completed_after_checkout(): void
    {
        $schedule = $this->makeOnsiteShiftToday();

        AttendanceLog::create([
            'employee_id'       => $this->employee->id,
            'shift_schedule_id' => $schedule->id,
            'work_date'         => now()->toDateString(),
            'check_in_at'       => now(),
            'check_out_at'      => now()->addHours(8),
            'check_in_method'   => 'gps_ip',
            'check_out_method'  => 'gps_ip',
        ]);

        $response = $this->getMobileDashboard();

        $response->assertOk();
        $response->assertSee('Hoàn thành');
        $response->assertDontSee('id="btnCheckIn-' . $schedule->id . '"', false);
        $response->assertDontSee('id="btnCheckOut-' . $schedule->id . '"', false);
    }

    /**
     * User đăng nhập nhưng KHÔNG gắn với bản ghi Employee nào (VD admin phụ/kế toán chưa map
     * nhân viên): personalDashboard() truyền $employee = null. View mobile tham chiếu $employee ở
     * khối thông tin nhân viên và script GPS/thời tiết — mọi chỗ phải null-safe để không văng
     * "Undefined variable $employee" / gọi thuộc tính trên null. Khoá lại trang vẫn render bình thường.
     */
    public function test_mobile_dashboard_renders_for_user_without_employee(): void
    {
        $userNoEmployee = User::factory()->create();
        $userNoEmployee->assignRole('staff');

        $response = $this->actingAs($userNoEmployee)
            ->withSession(['view_mode' => 'mobile'])
            ->withHeaders(['User-Agent' => self::MOBILE_UA])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($userNoEmployee->name);
    }

    public function test_late_minutes_pulled_from_real_attendance_log(): void
    {
        $schedule = $this->makeOnsiteShiftToday();

        AttendanceLog::create([
            'employee_id'       => $this->employee->id,
            'shift_schedule_id' => $schedule->id,
            'work_date'         => now()->toDateString(),
            'check_in_at'       => now()->setTime(8, 20),
            'check_in_method'   => 'gps_ip',
            'late_minutes'      => 20,
        ]);

        $response = $this->getMobileDashboard();

        $response->assertOk();
        $response->assertSee('Trễ 20 phút');
    }
}
