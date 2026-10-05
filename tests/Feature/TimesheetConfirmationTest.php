<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\TimesheetConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimesheetConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $staffUser;
    private Employee $employee;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-own-timesheet-confirmation']));

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo([
            Permission::firstOrCreate(['name' => 'view-own-timesheet-confirmation']),
            Permission::firstOrCreate(['name' => 'view-timesheet-confirmations']),
            Permission::firstOrCreate(['name' => 'confirm-timesheet-on-behalf']),
        ]);

        $this->staffUser = User::factory()->create();
        $this->staffUser->assignRole('staff');

        $this->employee = Employee::create([
            'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'user_id' => $this->staffUser->id, 'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        Setting::setValue('timesheet_confirmation_enabled', '1');
    }

    public function test_employee_can_confirm_own_month(): void
    {
        $response = $this->actingAs($this->staffUser)->post(route('timesheet-confirmation.confirm'), [
            'month' => 7, 'year' => 2026,
        ]);

        $response->assertRedirect();

        $confirmation = TimesheetConfirmation::where('employee_id', $this->employee->id)
            ->where('month', 7)->where('year', 2026)->first();

        $this->assertNotNull($confirmation);
        $this->assertEquals('confirmed', $confirmation->status);
        $this->assertFalse($confirmation->is_proxy_confirmed);
        $this->assertEquals($this->staffUser->id, $confirmation->confirmed_by);
    }

    public function test_employee_can_unconfirm_own_month(): void
    {
        $this->actingAs($this->staffUser)->post(route('timesheet-confirmation.confirm'), ['month' => 7, 'year' => 2026]);

        $response = $this->actingAs($this->staffUser)->post(route('timesheet-confirmation.unconfirm'), [
            'month' => 7, 'year' => 2026,
        ]);

        $response->assertRedirect();

        $confirmation = TimesheetConfirmation::where('employee_id', $this->employee->id)
            ->where('month', 7)->where('year', 2026)->first();

        $this->assertEquals('pending', $confirmation->status);
        $this->assertNull($confirmation->confirmed_by);
    }

    public function test_admin_can_confirm_on_behalf_and_notification_is_sent(): void
    {
        $response = $this->actingAs($this->adminUser)->post(
            route('timesheet-confirmations.confirm', $this->employee),
            ['month' => 7, 'year' => 2026]
        );

        $response->assertRedirect();

        $confirmation = TimesheetConfirmation::where('employee_id', $this->employee->id)
            ->where('month', 7)->where('year', 2026)->first();

        $this->assertNotNull($confirmation);
        $this->assertEquals('confirmed', $confirmation->status);
        $this->assertTrue($confirmation->is_proxy_confirmed);
        $this->assertEquals($this->adminUser->id, $confirmation->confirmed_by);

        $notification = Notification::where('user_id', $this->staffUser->id)
            ->where('type', 'timesheet_confirmed_proxy')->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('7/2026', $notification->body);
        $this->assertStringContainsString($this->adminUser->name, $notification->body);
    }

    public function test_admin_detail_page_renders_overview_layout(): void
    {
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'work_date'   => '2026-07-15',
            'check_in_at' => '2026-07-15 09:00:00',
            'check_out_at' => '2026-07-15 18:00:00',
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('timesheet-confirmations.show', [
                $this->employee,
                'month' => 7,
                'year'  => 2026,
            ]))
            ->assertOk()
            ->assertSee('Tổng quan chấm công')
            ->assertSee('Chi tiết theo ngày')
            ->assertSee('Kỳ công')
            ->assertSee('data-testid="timesheet-toolbar"', false)
            ->assertDontSee('Chọn phương án hiển thị')
            ->assertDontSee('Tập trung duyệt');
    }

    public function test_multi_shift_day_displays_earlier_shift_first_regardless_of_insertion_order(): void
    {
        // Bug thực tế: bảng "Chi tiết theo ngày" hiển thị 2 dòng giờ vào/ra trong cùng 1 ô ngày
        // (đa ca) theo thứ tự TẠO BẢN GHI (id/created_at), không phải theo thứ tự thời gian thực —
        // cố ý tạo bản ghi ca TỐI (18:00) TRƯỚC ca sáng (11:00) để bài test thật sự kiểm chứng có
        // sort theo giờ vào chứ không phải tình cờ đúng thứ tự.
        // Dùng giờ lẻ, khó trùng với các chuỗi khác trên trang (asset hash, option giờ mặc định...)
        // — nếu không, assertSeeInOrder có thể pass "ăn may" do trùng chuỗi ở chỗ khác, không thật
        // sự kiểm chứng thứ tự đúng của bảng.
        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'work_date' => '2026-07-15',
            'check_in_at' => '2026-07-15 18:53:00', 'check_out_at' => '2026-07-16 00:19:00',
        ]);
        AttendanceLog::create([
            'employee_id' => $this->employee->id, 'work_date' => '2026-07-15',
            'check_in_at' => '2026-07-15 11:07:00', 'check_out_at' => '2026-07-15 15:42:00',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('timesheet-confirmations.show', [
                $this->employee,
                'month' => 7,
                'year'  => 2026,
            ]));

        $response->assertOk();

        // assertSeeInOrder không đáng tin ở đây: trang có CẢ block mobile lẫn desktop (ẩn/hiện
        // bằng CSS, không phải bị xoá khỏi DOM) cho cùng dữ liệu — 1 khớp "ăn may" giữa 2 block
        // khác nhau vẫn có thể pass dù thứ tự THẬT trong từng block bị sai. Dò vị trí thực tế của
        // từng mốc giờ trong response, chỉ tính occurrence ĐẦU TIÊN của mỗi giờ.
        $html = $response->getContent();
        $posMorningIn  = strpos($html, '11:07');
        $posMorningOut = strpos($html, '15:42');
        $posEveningIn  = strpos($html, '18:53');

        $this->assertNotFalse($posMorningIn, 'Không thấy giờ vào ca sáng 11:07 trong response.');
        $this->assertNotFalse($posEveningIn, 'Không thấy giờ vào ca tối 18:53 trong response.');
        $this->assertLessThan($posEveningIn, $posMorningIn, 'Ca sáng (11:07) phải hiển thị TRƯỚC ca tối (18:53).');
        $this->assertLessThan($posEveningIn, $posMorningOut, 'Giờ ra ca sáng (15:42) phải hiển thị TRƯỚC ca tối (18:53).');
    }

    public function test_employee_page_uses_shared_overview_layout_and_own_actions(): void
    {
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'work_date'   => '2026-07-15',
            'check_in_at' => '2026-07-15 09:00:00',
            'check_out_at' => '2026-07-15 18:00:00',
        ]);

        $this->actingAs($this->staffUser)
            ->get(route('timesheet-confirmation.index', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Tổng quan chấm công')
            ->assertSee('Chi tiết theo ngày')
            ->assertSee('Kỳ công')
            ->assertSee('Xác nhận bảng công')
            ->assertDontSee('Xác nhận hộ')
            ->assertDontSee('Danh sách');

        $this->actingAs($this->staffUser)
            ->post(route('timesheet-confirmation.confirm'), ['month' => 7, 'year' => 2026])
            ->assertRedirect();

        $this->actingAs($this->staffUser)
            ->get(route('timesheet-confirmation.index', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Huỷ xác nhận')
            ->assertDontSee('Xác nhận hộ');
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('timesheet-confirmation.index'))->assertRedirect(route('login'));
        $this->post(route('timesheet-confirmation.confirm'), ['month' => 7, 'year' => 2026])->assertRedirect(route('login'));
        $this->get(route('timesheet-confirmations.index'))->assertRedirect(route('login'));
        $this->get(route('timesheet-confirmations.show', $this->employee))->assertRedirect(route('login'));
        $this->post(route('timesheet-confirmations.confirm', $this->employee), ['month' => 7, 'year' => 2026])->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_access_admin_routes(): void
    {
        $response = $this->actingAs($this->staffUser)->get(route('timesheet-confirmations.index'));
        $response->assertStatus(403);

        $response = $this->actingAs($this->staffUser)->post(
            route('timesheet-confirmations.confirm', $this->employee),
            ['month' => 7, 'year' => 2026]
        );
        $response->assertStatus(403);
    }

    public function test_routes_return_404_when_feature_disabled(): void
    {
        Setting::setValue('timesheet_confirmation_enabled', '0');

        $this->actingAs($this->staffUser)->get(route('timesheet-confirmation.index'))->assertStatus(404);
        $this->actingAs($this->staffUser)->post(route('timesheet-confirmation.confirm'), ['month' => 7, 'year' => 2026])->assertStatus(404);
        $this->actingAs($this->adminUser)->get(route('timesheet-confirmations.index'))->assertStatus(404);
        $this->actingAs($this->adminUser)->get(route('timesheet-confirmations.show', $this->employee))->assertStatus(404);
        $this->actingAs($this->adminUser)->post(route('timesheet-confirmations.confirm', $this->employee), ['month' => 7, 'year' => 2026])->assertStatus(404);
    }

    public function test_confirming_already_confirmed_month_is_idempotent(): void
    {
        $this->actingAs($this->staffUser)->post(route('timesheet-confirmation.confirm'), ['month' => 7, 'year' => 2026]);
        $this->actingAs($this->staffUser)->post(route('timesheet-confirmation.confirm'), ['month' => 7, 'year' => 2026]);

        $count = TimesheetConfirmation::where('employee_id', $this->employee->id)
            ->where('month', 7)->where('year', 2026)->count();

        $this->assertEquals(1, $count);
    }

    public function test_editing_attendance_log_resets_confirmation_to_pending(): void
    {
        $this->actingAs($this->staffUser)->post(route('timesheet-confirmation.confirm'), ['month' => 7, 'year' => 2026]);

        $log = AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'work_date'   => '2026-07-15',
            'check_in_at' => '2026-07-15 09:00:00',
        ]);

        $confirmation = TimesheetConfirmation::where('employee_id', $this->employee->id)
            ->where('month', 7)->where('year', 2026)->first();
        $this->assertEquals('pending', $confirmation->status);

        // Re-confirm, then update the log — should reset again.
        $this->actingAs($this->staffUser)->post(route('timesheet-confirmation.confirm'), ['month' => 7, 'year' => 2026]);
        $log->update(['check_out_at' => '2026-07-15 18:00:00']);

        $confirmation->refresh();
        $this->assertEquals('pending', $confirmation->status);

        // Re-confirm, then delete the log — should reset again.
        $this->actingAs($this->staffUser)->post(route('timesheet-confirmation.confirm'), ['month' => 7, 'year' => 2026]);
        $log->delete();

        $confirmation->refresh();
        $this->assertEquals('pending', $confirmation->status);
    }

    public function test_reset_does_not_affect_other_employee_or_other_month(): void
    {
        $otherEmployee = Employee::create(['code' => 'EMP-02', 'name' => 'Trần Thị B', 'is_active' => true]);

        $this->actingAs($this->staffUser)->post(route('timesheet-confirmation.confirm'), ['month' => 7, 'year' => 2026]);

        // Log for a different employee, same month — should not reset $this->employee's confirmation.
        AttendanceLog::create([
            'employee_id' => $otherEmployee->id,
            'work_date'   => '2026-07-10',
            'check_in_at' => '2026-07-10 09:00:00',
        ]);

        $confirmation = TimesheetConfirmation::where('employee_id', $this->employee->id)
            ->where('month', 7)->where('year', 2026)->first();
        $this->assertEquals('confirmed', $confirmation->status);

        // Log for the same employee, a DIFFERENT month — should not reset July's confirmation.
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'work_date'   => '2026-08-05',
            'check_in_at' => '2026-08-05 09:00:00',
        ]);

        $confirmation->refresh();
        $this->assertEquals('confirmed', $confirmation->status);
    }
}
