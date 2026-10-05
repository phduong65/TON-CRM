<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['status' => 'active']);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function auth()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_index_returns_own_notifications_with_data_payload(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'penalty_created',
            'title' => 'Phiếu phạt mới',
            'data' => ['penalty_id' => 5],
        ]);

        $other = User::factory()->create(['status' => 'active']);
        Notification::create(['user_id' => $other->id, 'type' => 'general', 'title' => 'Khác']);

        $response = $this->auth()->getJson('/api/notifications');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(['penalty_id' => 5], $data[0]['data']);
        $this->assertEquals('penalty_created', $data[0]['type']);
    }

    public function test_index_serializes_empty_data_as_json_object(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'shift_checkin_reminder',
            'title' => 'Nhắc check-in',
            'data' => [],
        ]);

        $response = $this->auth()->getJson('/api/notifications');

        $response->assertOk();
        $payload = json_decode($response->getContent());

        $this->assertInstanceOf(\stdClass::class, $payload->data[0]->data);
    }

    public function test_unread_count_only_counts_own_unread(): void
    {
        Notification::create(['user_id' => $this->user->id, 'type' => 'general', 'title' => 'A']);
        Notification::create(['user_id' => $this->user->id, 'type' => 'general', 'title' => 'B', 'read_at' => now()]);

        $response = $this->auth()->getJson('/api/notifications/unread-count');

        $response->assertOk()->assertJson(['count' => 1]);
    }

    public function test_mark_read_updates_read_at(): void
    {
        $notification = Notification::create(['user_id' => $this->user->id, 'type' => 'general', 'title' => 'A']);

        $this->auth()->postJson("/api/notifications/{$notification->id}/read")->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_mark_others_notification_as_read(): void
    {
        $other = User::factory()->create(['status' => 'active']);
        $notification = Notification::create(['user_id' => $other->id, 'type' => 'general', 'title' => 'A']);

        $this->auth()->postJson("/api/notifications/{$notification->id}/read")->assertStatus(403);
    }

    public function test_mark_all_read(): void
    {
        Notification::create(['user_id' => $this->user->id, 'type' => 'general', 'title' => 'A']);
        Notification::create(['user_id' => $this->user->id, 'type' => 'general', 'title' => 'B']);

        $this->auth()->postJson('/api/notifications/read-all')->assertOk();

        $this->assertEquals(0, Notification::where('user_id', $this->user->id)->whereNull('read_at')->count());
    }
}
