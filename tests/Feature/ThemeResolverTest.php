<?php

namespace Tests\Feature;

use App\Models\Theme;
use App\Services\Theme\ThemeResolverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeResolverTest extends TestCase
{
    use RefreshDatabase;

    private ThemeResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ThemeResolverService::class);
        $this->resolver->clearCache();

        // Create default theme
        Theme::create([
            'name'       => 'Default TON-HR',
            'slug'       => 'default',
            'level'      => 1,
            'priority'   => 0,
            'status'     => 'active',
            'scope'      => ['login', 'app_header', 'dashboard_greeting'],
            'config'     => [
                'colors'   => ['accent' => '#2563EB', 'accentContrast' => '#FFFFFF', 'bgTint' => 'transparent'],
                'content'  => ['loginGreeting' => 'Chào mừng trở lại', 'loginSubtitle' => 'Quản lý nhân sự TON-HR'],
                'visual'   => ['decorations' => [], 'animation' => ['enabled' => false]],
                'settings' => ['maxDecorationCoverage' => 0.15],
            ],
        ]);
    }

    public function test_default_theme_is_returned_when_no_seasonal_themes_active(): void
    {
        $resolved = $this->resolver->resolve('login');

        $this->assertEquals('default', $resolved['slug']);
        $this->assertEquals('Default TON-HR', $resolved['name']);
        $this->assertEquals('#2563EB', $resolved['colors']['accent']);
    }

    public function test_higher_priority_active_theme_wins_over_lower_priority(): void
    {
        $now = Carbon::parse('2026-09-02 10:00:00', 'Asia/Ho_Chi_Minh');

        Theme::create([
            'name'       => 'Theme Priority 50',
            'slug'       => 'theme-50',
            'level'      => 1,
            'priority'   => 50,
            'status'     => 'active',
            'scope'      => ['login'],
            'config'     => [
                'colors'  => ['accent' => '#10B981'],
                'content' => ['loginGreeting' => 'Greeting 50'],
            ],
        ]);

        Theme::create([
            'name'       => 'Theme Priority 90',
            'slug'       => 'theme-90',
            'level'      => 2,
            'priority'   => 90,
            'status'     => 'active',
            'scope'      => ['login'],
            'config'     => [
                'colors'  => ['accent' => '#DC2626'],
                'content' => ['loginGreeting' => 'Greeting 90'],
            ],
        ]);

        $resolved = $this->resolver->resolve('login', $now);

        $this->assertEquals('theme-90', $resolved['slug']);
        $this->assertEquals('#DC2626', $resolved['colors']['accent']);
    }

    public function test_scheduled_theme_only_activates_within_window(): void
    {
        Theme::create([
            'name'       => 'Tết 2027',
            'slug'       => 'tet-2027',
            'level'      => 2,
            'priority'   => 100,
            'status'     => 'scheduled',
            'start_at'   => Carbon::parse('2027-02-01 00:00:00', 'Asia/Ho_Chi_Minh'),
            'end_at'     => Carbon::parse('2027-02-15 23:59:59', 'Asia/Ho_Chi_Minh'),
            'scope'      => ['login'],
            'config'     => [
                'colors'  => ['accent' => '#DC2626'],
                'content' => ['loginGreeting' => 'Chúc mừng năm mới 2027'],
            ],
        ]);

        // Before window: should resolve default
        $before = Carbon::parse('2027-01-20 12:00:00', 'Asia/Ho_Chi_Minh');
        $resolvedBefore = $this->resolver->resolve('login', $before);
        $this->assertEquals('default', $resolvedBefore['slug']);

        // In window: should resolve Tết 2027
        $inWindow = Carbon::parse('2027-02-05 12:00:00', 'Asia/Ho_Chi_Minh');
        $resolvedInWindow = $this->resolver->resolve('login', $inWindow);
        $this->assertEquals('tet-2027', $resolvedInWindow['slug']);

        // After window: should fall back to default
        $after = Carbon::parse('2027-02-20 12:00:00', 'Asia/Ho_Chi_Minh');
        $resolvedAfter = $this->resolver->resolve('login', $after);
        $this->assertEquals('default', $resolvedAfter['slug']);
    }

    public function test_paused_theme_is_never_resolved(): void
    {
        Theme::create([
            'name'       => 'Paused Theme',
            'slug'       => 'paused-theme',
            'level'      => 1,
            'priority'   => 999,
            'status'     => 'paused',
            'scope'      => ['login'],
            'config'     => [
                'colors' => ['accent' => '#000000'],
            ],
        ]);

        $resolved = $this->resolver->resolve('login');
        $this->assertEquals('default', $resolved['slug']);
    }

    public function test_detect_conflicts_identifies_overlapping_themes(): void
    {
        Theme::create([
            'name'       => 'Sự kiện A',
            'slug'       => 'event-a',
            'level'      => 1,
            'priority'   => 80,
            'status'     => 'scheduled',
            'start_at'   => Carbon::parse('2026-10-01 00:00:00', 'Asia/Ho_Chi_Minh'),
            'end_at'     => Carbon::parse('2026-10-10 23:59:59', 'Asia/Ho_Chi_Minh'),
            'scope'      => ['login'],
            'config'     => [],
        ]);

        Theme::create([
            'name'       => 'Sự kiện B',
            'slug'       => 'event-b',
            'level'      => 2,
            'priority'   => 90,
            'status'     => 'scheduled',
            'start_at'   => Carbon::parse('2026-10-05 00:00:00', 'Asia/Ho_Chi_Minh'),
            'end_at'     => Carbon::parse('2026-10-15 23:59:59', 'Asia/Ho_Chi_Minh'),
            'scope'      => ['login'],
            'config'     => [],
        ]);

        $conflicts = $this->resolver->detectConflicts();

        $this->assertNotEmpty($conflicts);
        $this->assertEquals('Sự kiện B', $conflicts[0]['winner_name']);
    }

    public function test_public_resolve_api_returns_json(): void
    {
        $response = $this->getJson(route('api.theme.resolve', ['surface' => 'login']));

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => ['slug', 'name', 'colors', 'content', 'visual', 'level'],
        ]);
        $response->assertJson([
            'success' => true,
            'data'    => ['slug' => 'default'],
        ]);
    }
}
