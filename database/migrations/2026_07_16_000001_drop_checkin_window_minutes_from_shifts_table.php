<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ tính năng chặn check-in/check-out ngoài khung giờ ca (early_checkin_minutes/
 * late_checkout_minutes) — theo yêu cầu nghiệp vụ, không còn giới hạn giờ check-in/check-out.
 * grace_late_minutes/grace_early_minutes (tính phút trễ/sớm cho kỷ luật) không bị ảnh hưởng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['early_checkin_minutes', 'late_checkout_minutes']);
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedInteger('early_checkin_minutes')->default(30)->after('grace_early_minutes');
            $table->unsignedInteger('late_checkout_minutes')->default(30)->after('early_checkin_minutes');
        });
    }
};
