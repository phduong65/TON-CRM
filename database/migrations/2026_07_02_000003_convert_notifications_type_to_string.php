<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * notifications.type từng là enum() cứng (MySQL ENUM / SQLite CHECK constraint) —
 * mỗi khi thêm loại thông báo mới (leave_created, swap_created, ...) lại phải sửa migration
 * cũ hoặc thêm ALTER MODIFY riêng cho MySQL (không chạy được trên SQLite test suite).
 * Chuyển sang string tự do, validate loại hợp lệ ở tầng ứng dụng (Notification::typeLabel() match).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type', 50)->default('general')->change();
        });
    }

    public function down(): void
    {
        // Không revert về enum — tránh mất dữ liệu type mới nếu đã có bản ghi ngoài danh sách cũ.
    }
};
