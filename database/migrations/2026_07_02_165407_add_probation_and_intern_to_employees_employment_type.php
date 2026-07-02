<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * employment_type từng là enum() cứng ['full_time','part_time'] — cần thêm 'probation'
 * (thử việc), 'intern' (thực tập). Theo đúng tiền lệ của attendance_logs.check_in_method
 * (xem migration 2026_07_02_000008_add_manual_to_attendance_logs_method_enums.php): chuyển
 * sang string tự do thay vì ALTER MODIFY enum riêng cho MySQL — tránh vỡ CHECK constraint
 * trên SQLite (test suite) mỗi khi cần thêm giá trị hợp lệ mới. Giá trị hợp lệ được validate
 * ở tầng ứng dụng (StoreEmployeeRequest/UpdateEmployeeRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employment_type', 20)->default('full_time')->change();
        });
    }

    public function down(): void
    {
        // Không revert về enum — tránh mất dữ liệu 'probation'/'intern' đã có nếu đã có bản ghi.
    }
};
