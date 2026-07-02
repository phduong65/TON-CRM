<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * check_in_method/check_out_method từng là enum() cứng ['gps','ip','gps_ip','wfh'] — cần thêm
 * 'manual' để quản lý duyệt yêu cầu "Lượt chấm công" (bổ sung/sửa check-in/check-out) trong module
 * Yêu cầu & Phê duyệt. Theo đúng tiền lệ của notifications.type (xem migration
 * 2026_07_02_000003_convert_notifications_type_to_string.php): chuyển sang string tự do thay vì
 * ALTER MODIFY enum riêng cho MySQL — tránh vỡ CHECK constraint trên SQLite (test suite) mỗi khi
 * cần thêm giá trị hợp lệ mới. Giá trị hợp lệ được validate ở tầng ứng dụng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('check_in_method', 20)->nullable()->change();
            $table->string('check_out_method', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Không revert về enum — tránh mất dữ liệu 'manual' đã có nếu đã có bản ghi.
    }
};
