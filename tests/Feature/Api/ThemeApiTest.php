<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_theme_to_dark(): void
    {
        $user  = User::factory()->create(['status' => 'active', 'theme' => 'light']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/me/theme', ['theme' => 'dark']);

        $response->assertOk()->assertJson(['ok' => true, 'theme' => 'dark']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'theme' => 'dark']);
    }

    public function test_invalid_theme_value_is_rejected(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/me/theme', ['theme' => 'blue']);

        $response->assertStatus(422);
    }
}
