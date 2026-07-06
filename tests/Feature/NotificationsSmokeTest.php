<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_category_tabs_and_filters_by_category(): void
    {
        $user = User::factory()->create();

        Notification::create(['user_id' => $user->id, 'type' => 'penalty_created', 'title' => 'Tiêu đề phiếu phạt ABC']);
        Notification::create(['user_id' => $user->id, 'type' => 'leave_created', 'title' => 'Tiêu đề nghỉ phép XYZ']);

        $response = $this->actingAs($user)->get(route('notifications.index'));
        $response->assertStatus(200);
        $response->assertSee('Phiếu phạt');
        $response->assertSee('Nghỉ phép');
        $response->assertSee('Tiêu đề phiếu phạt ABC');
        $response->assertSee('Tiêu đề nghỉ phép XYZ');

        // Lọc theo tab "Phiếu phạt" — chỉ danh sách chính (không phải dropdown chuông ở topbar,
        // vốn luôn hiển thị vài thông báo gần nhất bất kể filter) mới cần đúng theo category.
        $response = $this->actingAs($user)->get(route('notifications.index', ['category' => 'penalty']));
        $response->assertStatus(200);
        $response->assertViewHas('notifications', function ($notifications) {
            return $notifications->total() === 1
                && $notifications->first()->type === 'penalty_created';
        });
    }
}
