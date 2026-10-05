<?php

namespace Tests\Feature;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ?string $dummyCredentialsPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-settings']));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    protected function tearDown(): void
    {
        if ($this->dummyCredentialsPath && file_exists($this->dummyCredentialsPath)) {
            unlink($this->dummyCredentialsPath);
        }

        parent::tearDown();
    }

    public function test_settings_index_shows_firebase_not_configured_by_default(): void
    {
        config(['services.firebase.credentials' => '/path/does/not/exist.json']);

        $response = $this->actingAs($this->admin)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('Chưa cấu hình');
    }

    public function test_test_firebase_reports_not_configured_when_credentials_file_missing(): void
    {
        config(['services.firebase.credentials' => '/path/does/not/exist.json']);

        $response = $this->actingAs($this->admin)->postJson(route('settings.test-firebase'));

        $response->assertOk();
        $response->assertJson(['ok' => false, 'reason' => 'not_configured']);
    }

    public function test_test_firebase_reports_no_tokens_when_user_has_not_enabled_push(): void
    {
        $this->fakeFirebaseCredentials();

        $response = $this->actingAs($this->admin)->postJson(route('settings.test-firebase'));

        $response->assertOk();
        $response->assertJson(['ok' => false, 'reason' => 'no_tokens']);
    }

    public function test_test_firebase_reports_exception_when_send_fails(): void
    {
        $this->fakeFirebaseCredentials();

        FcmToken::create([
            'user_id' => $this->admin->id,
            'token'   => 'fake-token-for-test',
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('settings.test-firebase'));

        $response->assertOk();
        // Dummy credentials không phải service account thật nên Firebase SDK sẽ throw khi gửi —
        // chỉ cần xác nhận lỗi được bắt gọn gàng (không crash 500) và trả về ok=false.
        $response->assertJson(['ok' => false]);
        $response->assertJsonStructure(['ok', 'reason']);
    }

    public function test_user_without_manage_settings_permission_cannot_test_firebase(): void
    {
        $staffUser = User::factory()->create();

        $response = $this->actingAs($staffUser)->postJson(route('settings.test-firebase'));

        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login_when_testing_firebase(): void
    {
        $response = $this->post(route('settings.test-firebase'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Trỏ config Firebase tới 1 file JSON giả (tồn tại nhưng không phải service account thật)
     * để PushNotificationService::isEnabled() trả true — đủ để test nhánh "đã cấu hình".
     */
    private function fakeFirebaseCredentials(): void
    {
        $this->dummyCredentialsPath = sys_get_temp_dir() . '/fake-firebase-credentials-' . uniqid() . '.json';
        file_put_contents($this->dummyCredentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
        ]));

        config(['services.firebase.credentials' => $this->dummyCredentialsPath]);
    }
}
