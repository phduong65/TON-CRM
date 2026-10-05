<?php

namespace Tests\Feature;

use App\Models\Theme;
use App\Models\ThemeAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ThemesAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Theme $defaultTheme;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-settings']));

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->defaultTheme = Theme::create([
            'name'       => 'Default TON-HR',
            'slug'       => 'default',
            'level'      => 1,
            'priority'   => 0,
            'status'     => 'active',
            'scope'      => ['login', 'app_header', 'dashboard_greeting'],
            'config'     => [
                'colors'  => ['accent' => '#2563EB'],
                'content' => ['loginGreeting' => 'Chào mừng trở lại'],
            ],
        ]);
    }

    public function test_admin_can_access_themes_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('themes.index'));

        $response->assertOk();
        $response->assertSee('Quản lý chủ đề & sự kiện');
        $response->assertSee('Default TON-HR');
    }

    public function test_admin_can_create_theme(): void
    {
        $payload = [
            'name'               => 'Giáng Sinh 2026',
            'slug'               => 'noel-2026',
            'level'              => 2,
            'priority'           => 85,
            'status'             => 'scheduled',
            'start_at'           => '2026-12-20T00:00',
            'end_at'             => '2026-12-26T23:59',
            'accent_color'       => '#059669',
            'login_greeting'     => 'Merry Christmas 2026',
            'login_subtitle'     => 'Chúc mừng Giáng sinh an lành!',
            'dashboard_greeting' => 'Chúc mừng Giáng sinh, :name!',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('themes.store'), $payload);

        $response->assertRedirect(route('themes.index'));
        $this->assertDatabaseHas('themes', [
            'slug'     => 'noel-2026',
            'priority' => 85,
        ]);

        $this->assertDatabaseHas('theme_audit_logs', [
            'action' => 'created',
        ]);
    }

    public function test_admin_can_update_theme(): void
    {
        $theme = Theme::create([
            'name'     => 'Tết Cũ',
            'slug'     => 'tet-cu',
            'level'    => 2,
            'priority' => 80,
            'status'   => 'draft',
            'config'   => ['colors' => ['accent' => '#DC2626']],
        ]);

        $payload = [
            'name'           => 'Tết Nguyên Đán Mới',
            'slug'           => 'tet-cu',
            'level'          => 2,
            'priority'       => 95,
            'status'         => 'active',
            'accent_color'   => '#B91C1C',
            'login_greeting' => 'Chúc mừng năm mới phát tài!',
        ];

        $response = $this->actingAs($this->adminUser)->put(route('themes.update', $theme), $payload);

        $response->assertRedirect(route('themes.index'));
        $this->assertDatabaseHas('themes', [
            'id'       => $theme->id,
            'name'     => 'Tết Nguyên Đán Mới',
            'priority' => 95,
            'status'   => 'active',
        ]);
    }

    public function test_admin_can_toggle_theme_status(): void
    {
        $theme = Theme::create([
            'name'     => 'Theme Test Toggle',
            'slug'     => 'test-toggle',
            'level'    => 1,
            'priority' => 50,
            'status'   => 'active',
            'config'   => [],
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('themes.toggle', $theme));

        $response->assertRedirect(route('themes.index'));
        $this->assertEquals('paused', $theme->fresh()->status);

        // Toggle again to re-activate
        $this->actingAs($this->adminUser)->post(route('themes.toggle', $theme));
        $this->assertEquals('active', $theme->fresh()->status);
    }

    public function test_admin_can_duplicate_theme(): void
    {
        $theme = Theme::create([
            'name'     => 'Chủ đề gốc',
            'slug'     => 'chu-de-goc',
            'level'    => 2,
            'priority' => 70,
            'status'   => 'active',
            'config'   => ['colors' => ['accent' => '#9333EA']],
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('themes.duplicate', $theme));

        $response->assertRedirect(route('themes.index'));
        $this->assertDatabaseHas('themes', [
            'slug'   => 'chu-de-goc-copy',
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_trigger_emergency_rollback(): void
    {
        $theme = Theme::create([
            'name'     => 'Buggy Holiday Theme',
            'slug'     => 'buggy-holiday',
            'level'    => 2,
            'priority' => 90,
            'status'   => 'active',
            'config'   => [],
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('themes.rollback', $theme), [
            'reason' => 'Lỗi hiển thị CSS trên Chrome Android, tạm thời hoàn tác.',
        ]);

        $response->assertRedirect(route('themes.index'));
        $this->assertEquals('paused', $theme->fresh()->status);

        $this->assertDatabaseHas('theme_audit_logs', [
            'theme_id' => $theme->id,
            'action'   => 'rollback',
            'reason'   => 'Lỗi hiển thị CSS trên Chrome Android, tạm thời hoàn tác.',
        ]);
    }

    public function test_default_theme_cannot_be_deleted(): void
    {
        $response = $this->actingAs($this->adminUser)->delete(route('themes.destroy', $this->defaultTheme));

        $this->assertDatabaseHas('themes', ['slug' => 'default']);
    }

    public function test_non_default_theme_can_be_deleted(): void
    {
        $theme = Theme::create([
            'name'     => 'Theme to delete',
            'slug'     => 'theme-delete',
            'level'    => 1,
            'priority' => 10,
            'status'   => 'draft',
            'config'   => [],
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('themes.destroy', $theme));

        $response->assertRedirect(route('themes.index'));
        $this->assertDatabaseMissing('themes', ['id' => $theme->id]);
    }

    public function test_preview_render_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson(route('themes.preview.render'), [
            'theme_id'       => $this->defaultTheme->id,
            'surface'        => 'login',
            'simulated_time' => '2026-09-02T09:00',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'theme' => ['slug', 'name', 'colors'],
            'is_active',
            'status',
            'preview_url',
        ]);
    }
}
