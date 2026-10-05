<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * reversal_data: ảnh chụp trạng thái AttendanceLog TRƯỚC khi duyệt yêu cầu (attendance_correction /
 * time_change / late_early / overtime) — để khi XOÁ yêu cầu đã duyệt có thể đảo ngược chính xác
 * (khôi phục lại giá trị cũ, hoặc xoá bản ghi nếu yêu cầu là bên tạo mới). Xem
 * StaffRequestsController::approve()/destroy().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_requests', function (Blueprint $table) {
            $table->json('reversal_data')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('staff_requests', function (Blueprint $table) {
            $table->dropColumn('reversal_data');
        });
    }
};
