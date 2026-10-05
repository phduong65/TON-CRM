<?php

namespace Tests\Feature;

use Database\Seeders\RequestsSampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Đảm bảo RequestsSampleDataSeeder chạy được (không lỗi fillable/khoá ngoại) và sinh đủ dữ liệu
 * mẫu ở cả 3 trạng thái cho hub "Yêu cầu và Phê duyệt" — dùng để test thủ công tính năng
 * duyệt/từ chối/huỷ/xoá trên trình duyệt.
 */
class RequestsSampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_runs_and_creates_sample_requests_in_all_statuses(): void
    {
        $this->seed(RequestsSampleDataSeeder::class);

        $this->assertDatabaseHas('employees', ['code' => 'DEMO-REQ-A']);
        $this->assertDatabaseHas('employees', ['code' => 'DEMO-REQ-B']);

        $this->assertDatabaseCount('staff_requests', 5);
        $this->assertDatabaseHas('staff_requests', ['code' => 'DEMO-ATC-0001', 'status' => 'pending']);
        $this->assertDatabaseHas('staff_requests', ['code' => 'DEMO-BTR-0001', 'status' => 'approved']);
        $this->assertDatabaseHas('staff_requests', ['code' => 'DEMO-LE-0001', 'status' => 'rejected']);

        $this->assertDatabaseCount('leave_requests', 3);
        $this->assertDatabaseHas('leave_requests', ['code' => 'DEMO-LR-0001', 'status' => 'pending']);
        $this->assertDatabaseHas('leave_requests', ['code' => 'DEMO-LR-0002', 'status' => 'approved']);
        $this->assertDatabaseHas('leave_requests', ['code' => 'DEMO-LR-0003', 'status' => 'rejected']);

        $this->assertDatabaseCount('shift_swap_requests', 2);
        $this->assertDatabaseHas('shift_swap_requests', ['code' => 'DEMO-SWP-0001', 'status' => 'pending']);
        $this->assertDatabaseHas('shift_swap_requests', ['code' => 'DEMO-SWP-0002', 'status' => 'rejected']);
    }

    public function test_seeder_is_idempotent_when_run_twice(): void
    {
        $this->seed(RequestsSampleDataSeeder::class);
        $this->seed(RequestsSampleDataSeeder::class);

        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseCount('staff_requests', 5);
        $this->assertDatabaseCount('leave_requests', 3);
        $this->assertDatabaseCount('shift_swap_requests', 2);
    }
}
