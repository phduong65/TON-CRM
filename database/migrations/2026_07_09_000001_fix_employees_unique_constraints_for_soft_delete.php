<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trước migration này, `code`/`email` là unique đơn (không kèm deleted_at) nên sau khi "xóa vĩnh
 * viễn" một nhân viên (thực chất chỉ soft delete — xem EmployeesController::destroy()), mã/email
 * đó không bao giờ có thể dùng lại được vì hàng dữ liệu cũ vẫn còn tồn tại (chỉ ẩn qua deleted_at).
 * Đổi sang unique composite (cột, deleted_at) — MySQL coi mỗi NULL là khác nhau trong unique index
 * nên các nhân viên đang active (deleted_at NULL) vẫn bị ràng buộc duy nhất như cũ, còn hàng đã
 * soft-delete (deleted_at có giá trị) không còn chặn việc tạo mới với cùng code/email nữa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_code_unique');
            $table->dropUnique('employees_email_unique');
            $table->unique(['code', 'deleted_at']);
            $table->unique(['email', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['code', 'deleted_at']);
            $table->dropUnique(['email', 'deleted_at']);
            $table->unique('code');
            $table->unique('email');
        });
    }
};
