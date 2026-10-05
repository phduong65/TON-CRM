<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phạm vi áp dụng ngày nghỉ lễ:
 * - applies_to_all = true  -> áp dụng cho MỌI nhân viên (giữ nguyên hành vi cũ cho dữ liệu đã có).
 * - applies_to_all = false -> chỉ áp dụng cho các bộ phận (team) trong bảng phụ holiday_teams
 *   (mỗi team đã gắn 1 chi nhánh, nên bảng phụ này thể hiện đúng "theo chi nhánh + theo bộ phận").
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mặc định true để các ngày lễ đã tạo trước đây tiếp tục áp dụng toàn công ty như cũ.
        Schema::table('holidays', function (Blueprint $table) {
            $table->boolean('applies_to_all')->default(true)->after('name');
        });

        Schema::create('holiday_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holiday_id')->constrained('holidays')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['holiday_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_teams');
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn('applies_to_all');
        });
    }
};
