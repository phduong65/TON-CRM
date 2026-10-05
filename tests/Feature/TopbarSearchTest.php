<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TopbarSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_data_script_tag_contains_valid_parseable_json(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('id="topbar-search-data"', false);

        // Regression: Illuminate\Support\Js::from() wraps its output as a JS
        // "JSON.parse('...')" expression (for inline attributes/JS), not raw JSON — using it
        // inside a <script type="application/json"> data island breaks the topbar search JS,
        // which does its own JSON.parse() on the tag's textContent. Must stay plain json_encode().
        preg_match('/<script type="application\/json" id="topbar-search-data">(.*?)<\/script>/s', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'topbar-search-data script tag not found');
        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded);
        $this->assertJsonStringEqualsJsonString($matches[1], json_encode($decoded));
    }

    public function test_search_data_only_includes_items_the_user_has_permission_for(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        preg_match('/<script type="application\/json" id="topbar-search-data">(.*?)<\/script>/s', $response->getContent(), $matches);
        $labels = collect(json_decode($matches[1], true))->pluck('label');

        $this->assertTrue($labels->contains('Bảng điều khiển'));
        $this->assertFalse($labels->contains('Nhân viên'));
        $this->assertFalse($labels->contains('Báo cáo chấm công'));
        $this->assertFalse($labels->contains('Cài đặt'));
    }

    public function test_search_data_includes_permitted_items_for_admin(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-shift-schedules']));
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-attendance']));

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Dùng trang attendance-logs (không phải dashboard) — dashboard render thêm widget
        // Redzone không liên quan tới topbar, gây lỗi HAVING trên SQLite test DB cho vài role,
        // không phải lỗi do search topbar.
        $response = $this->actingAs($admin)->get(route('attendance-logs.index'));
        $response->assertStatus(200);

        preg_match('/<script type="application\/json" id="topbar-search-data">(.*?)<\/script>/s', $response->getContent(), $matches);
        $items = collect(json_decode($matches[1], true))->keyBy('label');

        $this->assertTrue($items->has('Xếp ca'));
        $this->assertEquals(route('shift-schedules.index'), $items['Xếp ca']['url']);
        $this->assertTrue($items->has('Báo cáo chấm công'));
        $this->assertEquals(route('attendance-logs.index'), $items['Báo cáo chấm công']['url']);
    }
}
