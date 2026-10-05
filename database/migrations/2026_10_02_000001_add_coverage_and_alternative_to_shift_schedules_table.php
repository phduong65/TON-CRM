<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('branch_id')->constrained('teams')->nullOnDelete();
            $table->string('alternative_group_id', 64)->nullable()->after('assignment_type');
            $table->index('alternative_group_id');
            $table->index(['branch_id', 'team_id', 'work_date']);
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->unique('shift_schedule_id', 'attendance_logs_shift_schedule_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropUnique('attendance_logs_shift_schedule_id_unique');
        });

        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'team_id', 'work_date']);
            $table->dropIndex(['alternative_group_id']);
            $table->dropForeign(['team_id']);
            $table->dropColumn(['team_id', 'alternative_group_id']);
        });
    }
};
