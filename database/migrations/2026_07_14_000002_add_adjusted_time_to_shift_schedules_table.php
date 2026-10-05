<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            // Giờ vào/ra thực tế còn lại sau khi được duyệt nghỉ nửa ngày (hoặc nghỉ theo khung
            // giờ cụ thể) — null = dùng nguyên giờ ca mặc định. Chỉ override 1 phía (VD nghỉ sáng
            // chỉ set adjusted_start_time, giữ end_time gốc của ca). Xem LeaveRequestsController::approve()
            // và ShiftSchedule::effectiveShift().
            $table->time('adjusted_start_time')->nullable()->after('custom_is_wfh');
            $table->time('adjusted_end_time')->nullable()->after('adjusted_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropColumn(['adjusted_start_time', 'adjusted_end_time']);
        });
    }
};
