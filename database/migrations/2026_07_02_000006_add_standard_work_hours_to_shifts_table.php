<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // Số giờ = 1 công chuẩn của ca này (VD: Bếp 10h/công, Văn phòng 8h/công) —
            // dùng để quy đổi giờ làm thực tế sang "công" trong Bảng chấm công.
            $table->decimal('standard_work_hours', 4, 2)->default(8.00)->after('grace_early_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('standard_work_hours');
        });
    }
};
