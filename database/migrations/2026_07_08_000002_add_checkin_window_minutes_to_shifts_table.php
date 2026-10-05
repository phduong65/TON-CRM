<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // Khác với grace_late_minutes/grace_early_minutes (chỉ dùng để TÍNH số phút đi
            // trễ/về sớm phục vụ kỷ luật) — 2 cột này CHẶN hẳn hành động check-in/check-out
            // nếu ngoài khung giờ, để tránh nhân viên chấm công nhầm ca (VD: mới 10h40 mà bấm
            // check-in ca 18h-24h). early_checkin_minutes giới hạn được check-in sớm trước
            // start_time bao nhiêu phút; late_checkout_minutes giới hạn được check-out trễ sau
            // end_time bao nhiêu phút.
            $table->unsignedInteger('early_checkin_minutes')->default(30)->after('grace_early_minutes');
            $table->unsignedInteger('late_checkout_minutes')->default(30)->after('early_checkin_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['early_checkin_minutes', 'late_checkout_minutes']);
        });
    }
};
