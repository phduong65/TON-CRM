<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bản ghi chấm công NGHỈ LỄ tự tạo (source='holiday'): khối được nghỉ lễ tự được ghi công nghỉ lễ
 * mà không cần check-in. holiday_id để đảo ngược (xoá khi tắt/xoá lễ) và hiển thị tên lễ.
 * Xem HolidayApplicationService + AttendanceTimesheetBuilder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('holiday_id')->nullable()->after('shift_schedule_id')
                ->constrained('holidays')->nullOnDelete();
            $table->string('source', 20)->nullable()->after('holiday_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('holiday_id');
            $table->dropColumn('source');
        });
    }
};
