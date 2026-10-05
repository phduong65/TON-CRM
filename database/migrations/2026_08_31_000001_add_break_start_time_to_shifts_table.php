<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giờ bắt đầu nghỉ giữa ca (VD 12:00) — kết hợp với break_minutes (thời lượng) để biết CHÍNH XÁC
 * khoảng nghỉ nằm ở đâu trong ca. Dùng để tách "nghỉ nửa ngày" theo GIỜ CÔNG thực (bỏ qua giờ
 * nghỉ) thay vì chia cứng 12:00/13:00 — xem Shift::halfDaySplitTime()/workMinutesInWindow() và
 * LeaveRequestsController. Nullable: ca không cấu hình giờ nghỉ cụ thể vẫn chạy (fallback chia
 * tại điểm giữa giờ công tính từ đầu ca).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->time('break_start_time')->nullable()->after('break_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('break_start_time');
        });
    }
};
