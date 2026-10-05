<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shift_schedule_id dùng nullOnDelete() — khi ca xếp bị xoá/sửa lại sau đó (VD xếp lại lịch
 * theo tuần), các AttendanceLog cũ mất luôn tham chiếu ca, khiến netWorkedHours()/computeCong()
 * rơi về công thức mặc định (không rõ giờ ca để "kẹp" giờ vào/ra sớm/muộn), làm "Giờ công"/"Công"
 * hiển thị sai cho dữ liệu chấm công đã có từ trước. Chụp lại (snapshot) các thông số giờ ca ngay
 * lúc check-in để tính công không còn phụ thuộc vào việc ShiftSchedule có còn tồn tại hay không.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->time('shift_start_time')->nullable()->after('shift_schedule_id');
            $table->time('shift_end_time')->nullable()->after('shift_start_time');
            $table->integer('shift_break_minutes')->nullable()->after('shift_end_time');
            $table->boolean('shift_is_overnight')->nullable()->after('shift_break_minutes');
            $table->string('shift_type')->nullable()->after('shift_is_overnight');
            $table->decimal('shift_standard_work_hours', 5, 2)->nullable()->after('shift_type');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn([
                'shift_start_time',
                'shift_end_time',
                'shift_break_minutes',
                'shift_is_overnight',
                'shift_type',
                'shift_standard_work_hours',
            ]);
        });
    }
};
