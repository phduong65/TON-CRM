<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Số giờ tăng ca đã được duyệt (yêu cầu "Tăng ca" trong hub Yêu cầu & Phê duyệt), cộng thêm
 * vào giờ làm thực tế khi quy đổi "công" — xem AttendanceLog::computeCong().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->decimal('overtime_hours', 5, 2)->default(0)->after('full_credit');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn('overtime_hours');
        });
    }
};
