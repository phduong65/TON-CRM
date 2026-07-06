<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            // Lưu vết thiết bị (User-Agent trình duyệt) dùng để chấm công — dùng để cảnh báo
            // khi check-out bằng thiết bị khác với lúc check-in (không chặn, chỉ cảnh báo).
            $table->string('check_in_device', 255)->nullable()->after('check_in_location_id');
            $table->string('check_out_device', 255)->nullable()->after('check_out_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['check_in_device', 'check_out_device']);
        });
    }
};
