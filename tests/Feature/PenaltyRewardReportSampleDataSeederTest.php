<?php

namespace Tests\Feature;

use Database\Seeders\PenaltyRewardReportSampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Đảm bảo PenaltyRewardReportSampleDataSeeder chạy được (không lỗi fillable/khoá ngoại) và
 * sinh đủ dữ liệu mẫu ở cả 3 trạng thái cho Phiếu phạt/Phiếu thưởng/Báo cáo chéo — dùng để
 * test thủ công tính năng xoá trên trình duyệt.
 */
class PenaltyRewardReportSampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_runs_and_creates_sample_records_in_all_statuses(): void
    {
        $this->seed(PenaltyRewardReportSampleDataSeeder::class);

        $this->assertDatabaseHas('employees', ['code' => 'DEMO-PNL-A']);
        $this->assertDatabaseHas('employees', ['code' => 'DEMO-PNL-B']);

        $this->assertDatabaseCount('penalties', 3);
        $this->assertDatabaseHas('penalties', ['code' => 'DEMO-PNL-0001', 'status' => 'pending']);
        $this->assertDatabaseHas('penalties', ['code' => 'DEMO-PNL-0002', 'status' => 'approved']);
        $this->assertDatabaseHas('penalties', ['code' => 'DEMO-PNL-0003', 'status' => 'rejected']);

        $this->assertDatabaseCount('rewards', 3);
        $this->assertDatabaseHas('rewards', ['code' => 'DEMO-RWD-0001', 'status' => 'pending']);
        $this->assertDatabaseHas('rewards', ['code' => 'DEMO-RWD-0002', 'status' => 'approved']);
        $this->assertDatabaseHas('rewards', ['code' => 'DEMO-RWD-0003', 'status' => 'rejected']);

        $this->assertDatabaseCount('employee_reports', 3);
        $this->assertDatabaseHas('employee_reports', ['code' => 'DEMO-RPT-0001', 'status' => 'pending']);
        $this->assertDatabaseHas('employee_reports', ['code' => 'DEMO-RPT-0002', 'status' => 'approved']);
        $this->assertDatabaseHas('employee_reports', ['code' => 'DEMO-RPT-0003', 'status' => 'rejected']);
    }

    public function test_seeder_is_idempotent_when_run_twice(): void
    {
        $this->seed(PenaltyRewardReportSampleDataSeeder::class);
        $this->seed(PenaltyRewardReportSampleDataSeeder::class);

        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseCount('penalties', 3);
        $this->assertDatabaseCount('rewards', 3);
        $this->assertDatabaseCount('employee_reports', 3);
    }
}
