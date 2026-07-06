<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * status từng là enum() cứng ['active','inactive'] — cần thêm 'pending' cho tài khoản tự đăng ký
 * (routes/auth.php — register) đang chờ Admin duyệt trước khi được truy cập hệ thống. Theo đúng
 * tiền lệ của attendance_logs.check_in_method / employees.employment_type (xem migration
 * 2026_07_02_000008 và 2026_07_02_165407): chuyển sang string tự do thay vì ALTER MODIFY enum
 * riêng cho MySQL — tránh vỡ CHECK constraint trên SQLite (test suite) mỗi khi cần thêm giá trị
 * hợp lệ mới. Giá trị hợp lệ ('active'|'inactive'|'pending') được validate ở tầng ứng dụng
 * (RegisterController, LoginController, UsersController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->change();
        });
    }

    public function down(): void
    {
        // Không revert về enum — tránh mất dữ liệu 'pending' đã có nếu đã có bản ghi.
    }
};
