<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ca bị HUỶ tự động do ngày nghỉ lễ (khối được nghỉ). Lưu holiday_id để đảo ngược chính xác khi
 * xoá/tắt lễ hoặc bỏ bộ phận khỏi phạm vi (khôi phục ca về 'scheduled'). Xem HolidayApplicationService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->foreignId('holiday_id')->nullable()->after('status')
                ->constrained('holidays')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('holiday_id');
        });
    }
};
