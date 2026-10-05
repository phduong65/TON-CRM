<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Avatar nhân viên (users.avatar) phải hiện trên các trang danh sách/xếp hạng nếu nhân viên đã cài ảnh,
 * và rơi về chữ cái đầu nếu chưa — qua component <x-employee-avatar>.
 */
class EmployeeAvatarDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const AVATAR = 'uploads/avatars/test-avatar-xyz.jpg';

    private User $viewer;
    private Employee $withAvatar;
    private Employee $withoutAvatar;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->viewer = User::factory()->create();
        foreach (['view-employees', 'view-attendance', 'view-redzone', 'view-penalties'] as $perm) {
            $this->viewer->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        $team = Team::create(['code' => 'T-1', 'name' => 'Đội 1', 'branch_id' => $branch->id, 'is_active' => true]);

        $avatarUser = User::factory()->create(['avatar' => self::AVATAR]);
        $plainUser = User::factory()->create(['avatar' => null]);

        $this->withAvatar = Employee::create([
            'code' => 'EMP-A', 'name' => 'Anh Có Avatar', 'user_id' => $avatarUser->id,
            'branch_id' => $branch->id, 'team_id' => $team->id, 'is_active' => true,
        ]);
        $this->withoutAvatar = Employee::create([
            'code' => 'EMP-B', 'name' => 'Zed Không Avatar', 'user_id' => $plainUser->id,
            'branch_id' => $branch->id, 'team_id' => $team->id, 'is_active' => true,
        ]);
    }

    public function test_employees_index_shows_avatar_image_only_for_employee_who_set_one(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('employees.index'));

        $response->assertOk();
        $response->assertSee(self::AVATAR, false);
        // Nhân viên không có avatar → chữ cái đầu "Z" trong span, không có <img> thứ hai.
        $this->assertSame(1, substr_count($response->getContent(), self::AVATAR));
        $response->assertSee('aria-hidden="true">Z</span>', false);
    }

    public function test_attendance_logs_index_shows_avatar(): void
    {
        $today = Carbon::today();
        AttendanceLog::create([
            'employee_id' => $this->withAvatar->id, 'work_date' => $today->toDateString(),
            'check_in_at' => $today->copy()->setTime(9, 0), 'check_in_method' => 'gps_ip',
        ]);

        $this->actingAs($this->viewer)->get(route('attendance-logs.index'))
            ->assertOk()
            ->assertSee(self::AVATAR, false);
    }

    public function test_rankings_page_shows_avatar(): void
    {
        $this->actingAs($this->viewer)->get(route('rankings.index'))
            ->assertOk()
            ->assertSee(self::AVATAR, false);
    }

    public function test_user_without_avatar_falls_back_to_initials_component(): void
    {
        $html = view('components.employee-avatar', ['employee' => $this->withoutAvatar->load('user'), 'initials' => 2])->render();

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('ZE', $html);
    }

    public function test_user_with_avatar_renders_image_component(): void
    {
        $html = view('components.employee-avatar', ['employee' => $this->withAvatar->load('user')])->render();

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString(self::AVATAR, $html);
    }
}
