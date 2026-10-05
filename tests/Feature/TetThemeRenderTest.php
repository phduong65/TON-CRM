<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Team;
use App\Models\Theme;
use App\Models\User;
use App\Services\Theme\ThemeResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TetThemeRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-settings']));
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-employees']));

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $branch = Branch::create(['code' => 'BR-01', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        $team = Team::create(['branch_id' => $branch->id, 'code' => 'TEAM-01', 'name' => 'Team 1', 'is_active' => true]);

        Employee::create([
            'user_id'   => $this->adminUser->id,
            'branch_id' => $branch->id,
            'team_id'   => $team->id,
            'code'      => 'NV-ADMIN',
            'name'      => 'Nguyễn Quản Trị',
            'email'     => $this->adminUser->email,
            'is_active' => true,
        ]);

        app(ThemeResolverService::class)->clearCache();

        Theme::create([
            'name'     => 'Tết Nguyên Đán',
            'slug'     => 'tet-nguyen-dan',
            'level'    => 2,
            'status'   => 'active',
            'priority' => 100,
            'start_at' => now()->subDay(),
            'end_at'   => now()->addDay(),
            'scope'    => ['login', 'app_header', 'dashboard_greeting'],
            'config'   => [
                'colors'  => [
                    'accent'          => '#DC2626',
                    'accentSecondary' => '#F59E0B',
                    'accentContrast'  => '#FFFFFF',
                    'bgTint'          => '#FEF2F2',
                ],
                'content' => [
                    'loginGreeting'     => '🧧 Chúc mừng năm mới',
                    'loginSubtitle'     => 'Kính chúc Quý nhân viên và Gia đình năm mới An Khang Thịnh Vượng!',
                    'dashboardGreeting' => '🧧 Chúc mừng năm mới, :name!',
                    'dashboardSubtitle' => 'Khởi đầu năm mới bứt phá mục tiêu và gặt hái nhiều thành công rực rỡ!',
                ],
                'visual'  => [
                    'banners' => [
                        'dashboard_horizontal' => 'assets/images/themes/tet-dashboard-banner.jpg',
                        'login_hero_desktop'   => 'assets/images/themes/tet-login-hero.jpg',
                        'login_banner_mobile'  => 'assets/images/themes/tet-login-mobile.jpg',
                    ],
                ],
            ],
        ]);
    }

    public function test_login_page_renders_tet_banners_and_greeting(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('🧧 Chúc mừng năm mới');
        $response->assertSee('assets/images/themes/tet-login-hero.jpg');
        $response->assertSee('assets/images/themes/tet-login-mobile.jpg');
    }

    public function test_dashboard_page_renders_tet_horizontal_banner(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('assets/images/themes/tet-dashboard-banner.jpg');
        $response->assertSee('🧧 Chúc mừng năm mới, Nguyễn Quản Trị!');
        $response->assertSee('Khởi đầu năm mới bứt phá mục tiêu');
    }
}
