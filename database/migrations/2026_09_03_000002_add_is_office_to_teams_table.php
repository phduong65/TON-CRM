<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đánh dấu bộ phận thuộc khối văn phòng. Dùng để mặc định tick sẵn khi tạo ngày nghỉ lễ (khối văn
 * phòng nghỉ lễ, tuyến nhà hàng làm bình thường). Admin tự tick/bỏ tick — có trường hợp đặc biệt.
 * Backfill: team đang có ≥1 nhân viên is_office=true xem như bộ phận văn phòng (điểm khởi đầu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_office')->default(false)->after('branch_id');
        });

        $officeTeamIds = DB::table('employees')
            ->where('is_office', true)
            ->whereNotNull('team_id')
            ->distinct()
            ->pluck('team_id');

        if ($officeTeamIds->isNotEmpty()) {
            DB::table('teams')->whereIn('id', $officeTeamIds)->update(['is_office' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('is_office');
        });
    }
};
