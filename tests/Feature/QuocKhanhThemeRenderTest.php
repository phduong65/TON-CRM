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

class QuocKhanhThemeRenderTest extends TestCase
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
            'name'     => 'Quốc khánh 2/9',
            'slug'     => 'quoc-khanh-2-9',
            'level'    => 2,
            'status'   => 'active',
            'priority' => 100,
            'start_at' => now()->subDay(),
            'end_at'   => now()->addDay(),
            'scope'    => ['login', 'app_header', 'dashboard_greeting', 'notification'],
            'config'   => [
                'colors'   => [
                    'accent'          => '#DC2626',
                    'accentSecondary' => '#F59E0B',
                    'accentContrast'  => '#FFFFFF',
                    'bgTint'          => '#FEF2F2',
                ],
                'content'  => [
                    'loginGreeting'     => '🇻🇳 Chào mừng Quốc khánh 2/9',
                    'loginSubtitle'     => 'Tự hào non sông Việt Nam — Đoàn kết, đổi mới và kiến tạo tương lai.',
                    'dashboardGreeting' => '🇻🇳 Chào mừng Quốc khánh 2/9, :name!',
                    'dashboardSubtitle' => 'Hòa chung khí thế non sông, phát huy tinh thần trách nhiệm và cống hiến hết mình!',
                    'trustLine'         => 'Hệ thống nội bộ TON Capital · Tự hào Việt Nam',
                ],
                'visual'   => [
                    'banners'     => [
                        'dashboard_horizontal' => 'assets/images/themes/quoc-khanh-dashboard-banner.jpg',
                        'login_hero_desktop'   => 'assets/images/themes/quoc-khanh-login-hero.jpg',
                        'login_banner_mobile'  => 'assets/images/themes/quoc-khanh-login-mobile.jpg',
                    ],
                ],
                'settings' => ['maxDecorationCoverage' => 0.20, 'allowPrimaryCtaOverride' => false],
            ],
        ]);
    }

    public function test_login_page_renders_quoc_khanh_banners_and_greeting(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('🇻🇳 Chào mừng Quốc khánh 2/9');
        $response->assertSee('assets/images/themes/quoc-khanh-login-hero.jpg');
        $response->assertSee('assets/images/themes/quoc-khanh-login-mobile.jpg');
        $response->assertSee('Kỷ Niệm 2/9');
    }

    public function test_dashboard_page_renders_quoc_khanh_horizontal_banner(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('assets/images/themes/quoc-khanh-dashboard-banner.jpg');
        $response->assertSee('🇻🇳 Chào mừng Quốc khánh 2/9, Nguyễn Quản Trị!');
        $response->assertSee('Hòa chung khí thế non sông');
        $response->assertSee('Kỷ Niệm 2/9 · Tự Hào Non Sông');
    }
}
