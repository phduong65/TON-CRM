<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * type vốn là enum() cứng ['attendance_correction','business_trip','late_early','time_change'] —
 * cần thêm 'overtime' (yêu cầu tăng ca). Theo đúng tiền lệ của users.status (xem migration
 * 2026_07_06_000002): chuyển sang string tự do thay vì ALTER MODIFY enum riêng cho MySQL, để
 * tránh vỡ CHECK constraint trên SQLite (test suite) mỗi khi cần thêm giá trị hợp lệ mới. Giá
 * trị hợp lệ được validate ở StoreStaffRequestRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_requests', function (Blueprint $table) {
            $table->string('type', 30)->change();
        });
    }

    public function down(): void
    {
        // Không revert về enum — tránh mất dữ liệu 'overtime' nếu đã có bản ghi.
    }
};
