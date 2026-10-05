<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectMobileDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_desktop_user_agent_loads_default_view()
    {
        $user = User::factory()->create(['status' => 'approved']);
        
        $response = $this->actingAs($user)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])
            ->get('/dashboard');

        $response->assertStatus(200);
        $this->assertEquals('desktop', view()->shared('viewMode'));
        $this->assertFalse(view()->shared('isMobileDevice'));
    }

    public function test_mobile_user_agent_uses_mobile_view_by_default()
    {
        $user = User::factory()->create(['status' => 'approved']);

        $response = $this->actingAs($user)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
            ])
            ->get('/dashboard');

        $response->assertStatus(200);
        $this->assertEquals('mobile', view()->shared('viewMode'));
        $this->assertTrue(view()->shared('isMobileDevice'));
        $this->assertTrue(view()->shared('isMobileView'));
    }

    public function test_auto_mode_on_mobile_device_returns_mobile()
    {
        $user = User::factory()->create(['status' => 'approved']);

        // Đã ghim desktop trước đó, sau đó chọn 'auto' trên thiết bị mobile
        // -> xoá ghim, quay lại tự nhận theo thiết bị = mobile.
        $response = $this->actingAs($user)
            ->withSession(['view_mode' => 'desktop'])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
            ])
            ->get('/dashboard?view_mode=auto');

        $response->assertStatus(200);
        $this->assertEquals('mobile', view()->shared('viewMode'));
    }

    public function test_view_mode_query_param_overrides_detection()
    {
        $user = User::factory()->create(['status' => 'approved']);
        
        // Force desktop on mobile device
        $response = $this->actingAs($user)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
            ])
            ->get('/dashboard?view_mode=desktop');

        $response->assertStatus(200);
        $this->assertEquals('desktop', view()->shared('viewMode'));
        $this->assertTrue(view()->shared('isMobileDevice')); // Physical device is still mobile
    }

    public function test_mobile_legacy_view_requires_explicit_override()
    {
        $user = User::factory()->create(['status' => 'approved']);

        $response = $this->actingAs($user)
            ->get('/dashboard?view_mode=mobile');

        $response->assertStatus(200);
        $this->assertEquals('mobile', view()->shared('viewMode'));
        $this->assertTrue(view()->shared('isMobileView'));
    }

    public function test_auto_mode_clears_legacy_override()
    {
        $user = User::factory()->create(['status' => 'approved']);

        $response = $this->actingAs($user)
            ->withSession(['view_mode' => 'mobile'])
            ->get('/dashboard?view_mode=auto');

        $response->assertStatus(200);
        $this->assertEquals('desktop', view()->shared('viewMode'));
    }
}
